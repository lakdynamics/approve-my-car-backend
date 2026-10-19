<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A long-lived token used only to obtain a new access token.
 *
 * Only a SHA-256 hash of the token is stored. All tokens issued to one
 * signed-in device share a family_id, so the whole chain can be revoked.
 */
#[Fillable(['family_id', 'token_hash', 'access_token_id', 'device_name', 'expires_at', 'ip_address', 'user_agent'])]
#[Hidden(['token_hash'])]
class RefreshToken extends Model
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
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Delete tokens once they have expired. Run daily by `model:prune`.
     *
     * Used tokens are kept until expiry so that reusing one is still
     * recognised as reuse rather than as an unknown token.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }
}
