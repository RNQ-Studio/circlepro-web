<?php

namespace App\Services;

use App\Models\EventDivision;
use App\Models\Organization;
use App\Models\Rating;
use App\Models\RatingHistory;
use App\Models\RatingPeriod;
use App\Models\ScoringSession;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\EventTier;
use App\Support\Enums\Gender;
use App\Support\Enums\RatingPeriodStatus;
use App\Support\Enums\RatingStatus;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RatingEngine
{
    /**
     * Compute and update ratings for all participants in an EventDivision.
     */
    public function computeDivisionRatings(EventDivision $division): void
    {
        DB::transaction(function () use ($division) {
            $event = $division->event;
            
            // 1. Gather checked-in participants with completed scoring sessions
            $registrations = $division->registrations()
                ->where('status', 'checked_in')
                ->get();

            $participantsData = [];
            foreach ($registrations as $reg) {
                $session = ScoringSession::where('event_division_id', $division->id)
                    ->where('user_id', $reg->user_id)
                    ->where('status', ScoringSessionStatus::Completed)
                    ->first();

                if ($session) {
                    $participantsData[] = [
                        'user_id' => $reg->user_id,
                        'registration_id' => $reg->id,
                        'score' => $session->total_score,
                        'max_score' => $session->max_possible_score > 0 ? $session->max_possible_score : 720,
                        'session' => $session,
                    ];
                }
            }

            $n = count($participantsData);
            if ($n < 2) {
                // Cannot calculate ratings with fewer than 2 participants
                $division->update([
                    'rating_status' => 'rated',
                    'sof_avg_rating' => 1500.00,
                ]);
                return;
            }

            // 2. Determine placements (sort by score desc, x_count desc, ten_count desc, miss_count asc)
            usort($participantsData, function ($a, $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                if ($a['session']->x_count !== $b['session']->x_count) {
                    return $b['session']->x_count <=> $a['session']->x_count;
                }
                if ($a['session']->ten_count !== $b['session']->ten_count) {
                    return $b['session']->ten_count <=> $a['session']->ten_count;
                }
                return $a['session']->miss_count <=> $b['session']->miss_count;
            });

            // Add rank placement index (1-based)
            foreach ($participantsData as $idx => &$p) {
                $p['placement'] = $idx + 1;
            }
            unset($p);

            // 3. Resolve organization scope
            $platformOrg = Organization::where('slug', 'manahpro')->first();
            $orgId = $event->organization_id ?? $platformOrg->id;

            // Get or create active Rating Period for this month
            $periodMonth = Carbon::parse($event->starts_at ?? now())->startOfMonth();
            $period = RatingPeriod::firstOrCreate(
                ['organization_id' => $orgId, 'period_month' => $periodMonth],
                ['status' => RatingPeriodStatus::Open]
            );

            // 4. Retrieve or initialize current rating state for each participant
            // We lock ratings to prevent concurrent modifications
            $ratings = [];
            $sofSum = 0;
            
            $genderVal = Gender::Male; // Default fallback
            if ($division->gender) {
                $genderVal = $division->gender;
            }

            $ageVal = AgeGroup::Dewasa; // Default fallback
            if ($division->age_group) {
                $ageVal = $division->age_group;
            }

            $distVal = DistanceCategory::D70m; // Default fallback
            if ($division->distance) {
                $distVal = $division->distance;
            }

            foreach ($participantsData as &$p) {
                $rating = Rating::where([
                    'organization_id' => $orgId,
                    'user_id' => $p['user_id'],
                    'bow_class' => $division->bow_class,
                    'gender' => $genderVal,
                    'age_group' => $ageVal,
                    'distance_category' => $distVal,
                ])->lockForUpdate()->first();

                if (!$rating) {
                    $rating = new Rating([
                        'organization_id' => $orgId,
                        'user_id' => $p['user_id'],
                        'bow_class' => $division->bow_class,
                        'gender' => $genderVal,
                        'age_group' => $ageVal,
                        'distance_category' => $distVal,
                        'mu' => 1500.0000,
                        'phi' => 350.0000,
                        'sigma' => 0.060000,
                        'display_rating' => 800.00,
                        'status' => RatingStatus::Provisional,
                        'events_count' => 0,
                    ]);
                }

                $ratings[$p['user_id']] = $rating;
                $sofSum += $rating->mu;
            }
            unset($p);

            // Calculate SoF (Strength of Field)
            $sof = $sofSum / $n;

            // 5. Compute Normalized Performance Score (NPS) for each participant
            foreach ($participantsData as &$p) {
                $p['nps'] = ($p['score'] / $p['max_score']) * 1000.00;
            }
            unset($p);

            // 6. Glicko-2 Calculations
            $newRatingsData = [];
            $tau = 0.5; // system constant

            foreach ($participantsData as $p) {
                $userId = $p['user_id'];
                $currRating = $ratings[$userId];

                // Convert to Glicko-2 scale
                $mu = ($currRating->mu - 1500.0) / 173.7178;
                $phi = $currRating->phi / 173.7178;
                $sigma = $currRating->sigma;

                $opponents = [];
                $outcomes = [];

                foreach ($participantsData as $opp) {
                    if ($opp['user_id'] === $userId) {
                        continue;
                    }

                    $oppRating = $ratings[$opp['user_id']];
                    $opponents[] = [
                        'mu' => ($oppRating->mu - 1500.0) / 173.7178,
                        'phi' => $oppRating->phi / 173.7178,
                    ];

                    // Score differential outcome (sigmoid)
                    $outcomes[] = 1.0 / (1.0 + exp(-0.01 * ($p['nps'] - $opp['nps'])));
                }

                // Glicko-2 Step 2: Compute g(phi) and E(mu, mu_j, phi_j) for each opponent
                $gValues = [];
                $eValues = [];
                for ($j = 0; $j < count($opponents); $j++) {
                    $g = 1.0 / sqrt(1.0 + 3.0 * pow($opponents[$j]['phi'], 2) / pow(pi(), 2));
                    $e = 1.0 / (1.0 + exp(-$g * ($mu - $opponents[$j]['mu'])));
                    
                    $gValues[] = $g;
                    $eValues[] = $e;
                }

                // Glicko-2 Step 3: Compute estimated variance v
                $vInv = 0.0;
                for ($j = 0; $j < count($opponents); $j++) {
                    $vInv += pow($gValues[$j], 2) * $eValues[$j] * (1.0 - $eValues[$j]);
                }
                
                // If vInv is zero (all outcomes are extreme/identical), fallback to a small variance bound
                $v = $vInv > 0 ? (1.0 / $vInv) : 1000.0;

                // Glicko-2 Step 4: Compute delta
                $deltaSum = 0.0;
                for ($j = 0; $j < count($opponents); $j++) {
                    $deltaSum += $gValues[$j] * ($outcomes[$j] - $eValues[$j]);
                }
                $delta = $v * $deltaSum;

                // Glicko-2 Step 5: Update Volatility (Illinois root finding method)
                $a = log(pow($sigma, 2));
                $f = function ($x) use ($phi, $v, $delta, $a, $tau) {
                    $ex = exp($x);
                    $num = $ex * (pow($delta, 2) - pow($phi, 2) - $v - $ex);
                    $den = 2.0 * pow(pow($phi, 2) + $v + $ex, 2);
                    return ($num / $den) - (($x - $a) / pow($tau, 2));
                };

                // Bracket root finding bounds
                if (pow($delta, 2) > (pow($phi, 2) + $v)) {
                    $A = $a;
                    $B = log(pow($delta, 2) - pow($phi, 2) - $v);
                } else {
                    $B = $a;
                    $k = 1;
                    while ($f($a - $k * $tau) < 0) {
                        $k++;
                    }
                    $A = $a - $k * $tau;
                }

                $fA = $f($A);
                $fB = $f($B);
                
                $maxIterations = 50;
                $iterations = 0;
                $C = $A;

                while (abs($B - $A) > 0.000001 && $iterations < $maxIterations) {
                    $C = $A + ($A - $B) * $fA / ($fB - $fA);
                    $fC = $f($C);
                    
                    if ($fC * $fB < 0) {
                        $A = $B;
                        $fA = $fB;
                    } else {
                        $fA = $fA / 2.0;
                    }
                    
                    $B = $C;
                    $fB = $fC;
                    $iterations++;
                }

                $newSigma = exp($C / 2.0);

                // Glicko-2 Step 6: Compute pre-rating deviation
                $phiStar = sqrt(pow($phi, 2) + pow($newSigma, 2));

                // Glicko-2 Step 7: Update rating deviation and rating
                $newPhi = 1.0 / sqrt((1.0 / pow($phiStar, 2)) + (1.0 / $v));
                
                $muChangeSum = 0.0;
                for ($j = 0; $j < count($opponents); $j++) {
                    $muChangeSum += $gValues[$j] * ($outcomes[$j] - $eValues[$j]);
                }
                $newMu = $mu + pow($newPhi, 2) * $muChangeSum;

                // 7. Apply Dynamic K-Factor Scaling to Rating Change
                $eventsCount = $currRating->events_count;
                if ($eventsCount < 5) {
                    $kBase = 40.0;
                } elseif ($eventsCount < 20) {
                    $kBase = 32.0;
                } elseif ($eventsCount < 50) {
                    $kBase = 24.0;
                } else {
                    $kBase = 16.0;
                }

                // Event tier multiplier
                $tMult = 1.0;
                if ($event->tier) {
                    $tierVal = $event->tier;
                    if ($tierVal instanceof EventTier) {
                        $tierVal = $tierVal->value;
                    }
                    switch ($tierVal) {
                        case 'S': $tMult = 1.5; break;
                        case 'A': $tMult = 1.2; break;
                        case 'B': $tMult = 1.0; break;
                        case 'C': $tMult = 0.7; break;
                        case 'D': $tMult = 0.4; break;
                    }
                }

                // Participation multiplier
                $pMult = min(1.3, 0.7 + 0.02 * $n);

                // Format multiplier
                $fMult = 1.0;
                if ($event->format) {
                    $formatVal = $event->format;
                    switch ($formatVal) {
                        case 'ranking_round': $fMult = 1.0; break;
                        case 'half_round': $fMult = 0.7; break;
                        case 'match_play': $fMult = 0.9; break;
                        case 'elimination': $fMult = 0.8; break;
                    }
                }

                $kEffective = $kBase * $tMult * $pMult * $fMult;
                $changeMultiplier = $kEffective / 24.0;

                // Apply K-factor multiplier on Glicko-2 scale
                $muChange = $newMu - $mu;
                $muAdjusted = $mu + ($muChange * $changeMultiplier);

                // Convert back to original scale
                $finalMu = 173.7178 * $muAdjusted + 1500.0;
                $finalPhi = 173.7178 * $newPhi;

                // display_rating = finalMu - 2 * finalPhi
                $displayRating = $finalMu - 2.0 * $finalPhi;
                if ($displayRating < 100.0) {
                    $displayRating = 100.0; // Lower bound cap
                }

                $newRatingsData[$userId] = [
                    'mu' => $finalMu,
                    'phi' => $finalPhi,
                    'sigma' => $newSigma,
                    'display_rating' => $displayRating,
                    'k_effective' => $kEffective,
                    'score_achieved' => $p['score'],
                    'nps' => $p['nps'],
                    'placement' => $p['placement'],
                ];
            }

            // 8. Persist new rating state and log history
            $eventDate = Carbon::parse($event->starts_at ?? now())->toDateString();

            foreach ($participantsData as $p) {
                $userId = $p['user_id'];
                $currRating = $ratings[$userId];
                $newData = $newRatingsData[$userId];

                $muBefore = $currRating->mu;
                $phiBefore = $currRating->phi;
                $sigmaBefore = $currRating->sigma;
                $displayBefore = $currRating->display_rating;

                $newEventsCount = $currRating->events_count + 1;
                
                // Determine new status based on count of rated events
                $newStatus = RatingStatus::Provisional;
                if ($newEventsCount >= 10) {
                    $newStatus = RatingStatus::Established;
                } elseif ($newEventsCount >= 3) {
                    $newStatus = RatingStatus::Ranked;
                }

                $peakDisplay = $currRating->peak_display_rating;
                if (is_null($peakDisplay) || $newData['display_rating'] > $peakDisplay) {
                    $peakDisplay = $newData['display_rating'];
                }

                // Update rating record
                $currRating->fill([
                    'mu' => $newData['mu'],
                    'phi' => $newData['phi'],
                    'sigma' => $newData['sigma'],
                    'display_rating' => $newData['display_rating'],
                    'status' => $newStatus,
                    'events_count' => $newEventsCount,
                    'peak_display_rating' => $peakDisplay,
                    'last_event_date' => $eventDate,
                ]);
                $currRating->save();

                // Log rating history entry
                RatingHistory::create([
                    'rating_id' => $currRating->id,
                    'user_id' => $userId,
                    'event_division_id' => $division->id,
                    'rating_period_id' => $period->id,
                    'mu_before' => $muBefore,
                    'mu_after' => $newData['mu'],
                    'phi_before' => $phiBefore,
                    'phi_after' => $newData['phi'],
                    'sigma_before' => $sigmaBefore,
                    'sigma_after' => $newData['sigma'],
                    'display_before' => $displayBefore,
                    'display_after' => $newData['display_rating'],
                    'score_achieved' => $newData['score_achieved'],
                    'nps' => $newData['nps'],
                    'placement' => $newData['placement'],
                    'num_participants' => $n,
                    'event_tier' => $event->tier,
                    'k_effective' => $newData['k_effective'],
                    'is_manual_override' => false,
                    'computed_at' => now(),
                ]);
            }

            // Update EventDivision rating state
            $division->update([
                'rating_status' => 'rated',
                'sof_avg_rating' => $sof,
            ]);
        });
    }

    /**
     * Apply monthly decay (increase rating uncertainty phi) to inactive archers.
     */
    public function applyMonthlyDecay(Organization $org, Carbon $date): void
    {
        DB::transaction(function () use ($org, $date) {
            $periodMonth = $date->copy()->startOfMonth();
            
            // Find or create the period record
            $period = RatingPeriod::firstOrCreate(
                ['organization_id' => $org->id, 'period_month' => $periodMonth],
                ['status' => RatingPeriodStatus::Open]
            );

            if ($period->decay_applied_at) {
                // Decay already run for this period
                return;
            }

            // Find all ratings for this organization that were not updated in this month
            // or whose last_event_date is older than 1 month
            $decayConstant = 15.0; // Uncertainty inflation constant per month
            $phiMax = 350.0;

            $ratings = Rating::where('organization_id', $org->id)
                ->where(function ($query) use ($periodMonth) {
                    $query->whereNull('last_event_date')
                        ->orWhere('last_event_date', '<', $periodMonth->toDateString());
                })
                ->get();

            foreach ($ratings as $rating) {
                $phiBefore = $rating->phi;
                
                // Calculate months inactive
                $lastEvent = $rating->last_event_date ?? $rating->created_at;
                $monthsInactive = $lastEvent->diffInMonths($periodMonth);
                if ($monthsInactive < 1) {
                    $monthsInactive = 1;
                }

                // phi_new = sqrt(phi_old^2 + c^2 * months)
                $newPhi = sqrt(pow($rating->phi, 2) + pow($decayConstant * $monthsInactive, 2));
                if ($newPhi > $phiMax) {
                    $newPhi = $phiMax;
                }

                $newDisplay = $rating->mu - 2.0 * $newPhi;
                if ($newDisplay < 100.0) {
                    $newDisplay = 100.0;
                }

                $ratingStatus = $rating->status;
                if ($monthsInactive >= 12) {
                    $ratingStatus = RatingStatus::Inactive;
                }

                $rating->update([
                    'phi' => $newPhi,
                    'display_rating' => $newDisplay,
                    'status' => $ratingStatus,
                ]);

                // Log a history entry for the decay
                RatingHistory::create([
                    'rating_id' => $rating->id,
                    'user_id' => $rating->user_id,
                    'event_division_id' => null,
                    'rating_period_id' => $period->id,
                    'mu_before' => $rating->mu,
                    'mu_after' => $rating->mu,
                    'phi_before' => $phiBefore,
                    'phi_after' => $newPhi,
                    'sigma_before' => $rating->sigma,
                    'sigma_after' => $rating->sigma,
                    'display_before' => $rating->display_rating,
                    'display_after' => $newDisplay,
                    'is_manual_override' => false,
                    'computed_at' => now(),
                ]);
            }

            $period->update([
                'status' => RatingPeriodStatus::Closed,
                'decay_applied_at' => now(),
                'computed_at' => now(),
            ]);
        });
    }
}
