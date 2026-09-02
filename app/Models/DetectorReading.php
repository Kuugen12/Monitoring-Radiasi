<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetectorReading extends Model
{
    use HasFactory;

    protected $table = 'detector_readings';

    protected $fillable = [
        'detector_name',
        'rate',
        'dose_rate',
        'total',
        'status',
        'firebase_last_updated',
    ];
}
