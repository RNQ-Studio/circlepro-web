<?php

namespace App\Support\Enums;

/**
 * Archery bow classes / divisions used across scoring, events and ranking.
 */
enum BowClass: string
{
    case Recurve = 'recurve';
    case Compound = 'compound';
    case BarebowStandard = 'barebow_standard';
    case BarebowTradisional = 'barebow_tradisional';
    case Horsebow = 'horsebow';
    case Jemparingan = 'jemparingan';
}
