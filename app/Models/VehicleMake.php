<?php

namespace App\Models;

use Database\Factories\VehicleMakeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A make for the Add Vehicle dropdown (Toyota, Honda, Bajaj...).
 * Makes are hidden, never deleted, so vehicles keep their link.
 */
#[Fillable(['name', 'is_active'])]
class VehicleMake extends Model
{
    /** @use HasFactory<VehicleMakeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'make_id');
    }

    /**
     * @param  Builder<VehicleMake>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Find an active make by name, ignoring case.
     */
    public static function findActiveByName(string $name): ?self
    {
        return static::query()
            ->active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }
}
