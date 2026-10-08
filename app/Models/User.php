<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'phone', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    private ?array $permissionCache = null;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * Permissões efetivas: união das permissões de todos os papéis do usuário.
     * O resultado fica em memória por request para não recarregar a cada checagem.
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        return $this->permissionCache = $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        return (bool) array_intersect($slugs, $this->permissionSlugs());
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }

    /**
     * Iniciais para o avatar: primeira e última palavra do nome. Sem foto
     * uploadada ainda, é a identificação que não depende de arquivo algum.
     */
    public function initials(): string
    {
        $palavras = preg_split('/\s+/', trim($this->name) ?: 'Usuario', -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(mb_substr($palavras[0], 0, 1).mb_substr($palavras[count($palavras) - 1], 0, 1));
    }
}
