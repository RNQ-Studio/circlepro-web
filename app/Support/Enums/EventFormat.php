<?php

namespace App\Support\Enums;

/**
 * Event competition formats.
 */
enum EventFormat: string
{
    case RankingRound = 'ranking_round';
    case MatchPlay = 'match_play';
    case Elimination = 'elimination';
    case Field = 'field';
    case IndoorRound = 'indoor_round';
}
