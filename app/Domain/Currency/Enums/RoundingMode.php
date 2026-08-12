<?php

namespace App\Domain\Currency\Enums;

use Brick\Math\RoundingMode as BrickRoundingMode;

enum RoundingMode: string
{
    case HalfUp = 'HALF_UP';
    case HalfEven = 'HALF_EVEN';
    case Down = 'DOWN';
    case Up = 'UP';
    case Floor = 'FLOOR';
    case Ceiling = 'CEILING';

    public function toBrickMath(): BrickRoundingMode
    {
        return match ($this) {
            self::HalfUp => BrickRoundingMode::HalfUp,
            self::HalfEven => BrickRoundingMode::HalfEven,
            self::Down => BrickRoundingMode::Down,
            self::Up => BrickRoundingMode::Up,
            self::Floor => BrickRoundingMode::Floor,
            self::Ceiling => BrickRoundingMode::Ceiling,
        };
    }
}
