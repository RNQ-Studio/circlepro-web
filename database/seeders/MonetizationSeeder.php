<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\SubscriptionPlan;
use App\Support\Enums\AdStatus;
use App\Support\Enums\PlanAudience;
use App\Support\Enums\PlanInterval;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MonetizationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed User Subscription Plans
        SubscriptionPlan::updateOrCreate(
            ['code' => 'free'],
            [
                'name' => 'Free Tier',
                'audience' => PlanAudience::User->value,
                'price' => 0,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Maksimal 3 latihan / minggu', 'Statistik dasar', 'Iklan aktif'],
                'limits' => ['scoring_per_week' => 3],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro Archer',
                'audience' => PlanAudience::User->value,
                'price' => 49000,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Latihan tanpa batas', 'Statistik lengkap & grafik progres', 'Bebas iklan', 'Watermark scorecard premium'],
                'limits' => ['scoring_per_week' => -1],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['code' => 'elite'],
            [
                'name' => 'Elite Archer',
                'audience' => PlanAudience::User->value,
                'price' => 99000,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Semua fitur Pro', 'AI Insight & Analisis pengelompokan anak panah', 'Prioritas pendaftaran turnamen nasional', 'Badge eksklusif di profil'],
                'limits' => ['scoring_per_week' => -1],
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        // 2. Seed Club SaaS Subscription Plans
        SubscriptionPlan::updateOrCreate(
            ['code' => 'club_starter'],
            [
                'name' => 'Club Starter',
                'audience' => PlanAudience::Organization->value,
                'price' => 150000,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Maksimal 50 anggota aktif', 'Jadwal latihan klub rutin', 'Absensi QR Code'],
                'limits' => ['max_members' => 50],
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['code' => 'club_pro'],
            [
                'name' => 'Club Professional',
                'audience' => PlanAudience::Organization->value,
                'price' => 350000,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Maksimal 150 anggota aktif', 'Jadwal latihan tak terbatas', 'Dashboard statistik anggota & leaderboard klub'],
                'limits' => ['max_members' => 150],
                'is_active' => true,
                'sort_order' => 11,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['code' => 'club_elite'],
            [
                'name' => 'Club Enterprise',
                'audience' => PlanAudience::Organization->value,
                'price' => 750000,
                'interval' => PlanInterval::Monthly->value,
                'features' => ['Anggota aktif tanpa batas', 'Semua fitur Pro', 'Bantuan setup domain custom', 'White-label logo klub'],
                'limits' => ['max_members' => -1],
                'is_active' => true,
                'sort_order' => 12,
            ]
        );

        // 3. Seed test Ad Campaign & Ads
        $campaign = AdCampaign::create([
            'name' => 'ManahPro Gear Promo 2026',
            'budget' => 5000000,
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->addDays(30),
            'status' => AdStatus::Active->value,
            'targeting' => ['bow_classes' => ['recurve', 'compound']],
        ]);

        Ad::create([
            'ad_campaign_id' => $campaign->id,
            'placement' => 'feed',
            'image_url' => 'https://circlepro.web.id/assets/images/promo_bow.jpg',
            'title' => 'Busur Recurve Decut Honor-X',
            'body' => 'Dapatkan diskon 15% untuk pembelian busur recurve Decut Honor-X khusus pengguna ManahPro. Klik untuk melihat penawaran menarik ini!',
            'click_url' => 'https://manahgear.com/decut-honor-x',
        ]);

        Ad::create([
            'ad_campaign_id' => $campaign->id,
            'placement' => 'feed',
            'image_url' => 'https://circlepro.web.id/assets/images/promo_coaching.jpg',
            'title' => 'Sunnah Archery Academy',
            'body' => 'Bergabunglah dengan kelas intensif panahan Sunnah bersama pelatih bersertifikasi nasional. Diskon registrasi klub 10%.',
            'click_url' => 'https://sunnaharchery.id',
        ]);
    }
}
