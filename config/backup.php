<?php

/*
 * Aparato global do back‑up automático. Os limites que a empresa pode ajustar
 * (retenção, intervalo, tamanho e aviso) moram no banco, em `company_settings`,
 * e são lidos por `SettingsCatalog`. Aqui ficam só as rédeas da instalação:
 * onde o arquivo é escrito e de quantos em quantos registros a varredura lê
 * empresas, para um banco grande não virar carga desnecessária.
 */
return [
    'disk' => env('BACKUP_DISK', 'local'),

    'diretorio' => trim((string) env('BACKUP_DIRETORIO', 'empresas'), '/'),

    'lote' => max(1, (int) env('BACKUP_LOTE', 50)),
];
