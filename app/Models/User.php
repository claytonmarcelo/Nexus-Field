<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'phone', 'status', 'last_login_at', 'client_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_root' => 'boolean',
        ];
    }

    /**
     * `is_root` fica fora de `$fillable` de propósito: request nenhum cria conta
     * raiz. Ela nasce do seeder, que escreve a coluna direto.
     */
    protected static function booted(): void
    {
        // `deleting` dispara também no caminho do `forceDelete()` do SoftDeletes,
        // então uma guarda só cobre exclusão lógica e física.
        static::deleting(function (self $usuario) {
            if ($usuario->isRoot()) {
                $usuario->bloquear('não pode ser excluída');
            }
        });

        static::updating(function (self $usuario) {
            if (! $usuario->isRoot()) {
                return;
            }

            foreach (['email' => 'ter o e-mail trocado', 'company_id' => 'mudar de empresa'] as $coluna => $motivo) {
                if ($usuario->isDirty($coluna)) {
                    $usuario->bloquear($motivo);
                }
            }

            if ($usuario->isDirty('status') && $usuario->status !== 'active') {
                $usuario->bloquear('ser desativada');
            }
        });
    }

    private ?array $permissionCache = null;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** A ponte é `technicians.user_id`: quem tem ficha de campo tem uma ficha só. */
    public function technician(): HasOne
    {
        return $this->hasOne(Technician::class);
    }

    /**
     * A ponte do cliente final é `users.client_id`: a conta abre a própria
     * carteira e nada mais. Sem vínculo, a conta é do escritório e enxerga a
     * operação da empresa inteira — é isso que separa o papel de campo e de
     * cliente do papel administrativo.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isRoot(): bool
    {
        return (bool) $this->is_root;
    }

    /** @throws \RuntimeException sempre: é chamado só quando a conta raiz seria alterada */
    private function bloquear(string $motivo): void
    {
        throw new \RuntimeException(
            "A conta raiz ({$this->email}) {$motivo}: ela é a identidade administrativa do ".
            'sistema, e a regra dela vale mesmo quando o request vier de um administrador.'
        );
    }

    /**
     * Permissões efetivas: união das permissões de todos os papéis do usuário.
     * A conta raiz tem o catálogo inteiro — domínio absoluto não pode depender de
     * uma linha em `role_user`, que um `sync` de papéis apagaria.
     * O resultado fica em memória por request para não recarregar a cada checagem.
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->isRoot()) {
            return $this->permissionCache = Permission::query()->pluck('slug')->all();
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
        $palavras = preg_split('/\s+/', trim($this->name ?: '') ?: 'Usuario', -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // "Administrador (demo)" renderizava "A(" no avatar: só token que começa
        // com letra é nome. Sem nenhum, fica a marca.
        $palavras = array_values(array_filter(
            $palavras,
            static fn (string $palavra): bool => (bool) preg_match('/^[\p{L}]/u', $palavra),
        ));

        if ($palavras === []) {
            return 'NF';
        }

        $primeira = $palavras[0];
        $ultima = $palavras[count($palavras) - 1];

        // Nome de uma palavra só repetiria a mesma letra duas vezes ("AA").
        if ($primeira === $ultima) {
            return mb_strtoupper(mb_substr($primeira, 0, 2));
        }

        return mb_strtoupper(mb_substr($primeira, 0, 1).mb_substr($ultima, 0, 1));
    }
}
