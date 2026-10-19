<?php

namespace App\Models;

use App\Enums\VerificationCodePurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A code emailed to prove someone owns an email address.
 *
 * Only a hash of the code is stored. For registration, the payload holds
 * the pending sign-up details (names and password hash), encrypted.
 */
#[Fillable(['email', 'purpose', 'code_hash', 'payload', 'expires_at', 'ip_address'])]
#[Hidden(['code_hash', 'payload'])]
class EmailVerificationCode extends Model
{
    use MassPrunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => VerificationCodePurpose::class,
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Delete codes a day after they expire. Run daily by `model:prune`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
