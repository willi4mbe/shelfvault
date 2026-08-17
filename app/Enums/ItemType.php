<?php

namespace App\Enums;

enum ItemType: string
{
    case Film = 'film';
    case TvSeries = 'tv_series';
    case VideoGame = 'video_game';
    case BoardGame = 'board_game';
}
