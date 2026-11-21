<?php

/*
 * Configurações exclusivas do runtime de deploy.
 *
 * Toda variável de ambiente da aplicação é lida aqui. O restante do código
 * consulta `config('deploy.*')`, para que a configuração possa ser cacheada
 * sem perder valores e sem espalhar chamadas a env() fora desta pasta.
 */
return [

    /*
     * A Vercel termina o TLS no edge e repassa X-Forwarded-*. Como o container
     * não é alcançável diretamente pela internet, confiar no proxy é seguro.
     * Troque por uma lista de IPs se o projeto passar a ter outro balanceador.
     */
    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    /*
     * Hosts aceitos pelo middleware TrustHosts. Vazio = usar o host de APP_URL.
     * Inclua os domínios próprios e, se usar previews, o sufixo *.vercel.app.
     */
    'trusted_hosts' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', '')))
    )),

    /*
     * Em Container Runtime o filesystem é somente leitura. O entrypoint aponta
     * este caminho para /tmp, onde ficam logs, views compiladas e cache local.
     */
    'storage_path' => env('APP_STORAGE_PATH'),

    /*
     * Segredo enviado pela Vercel no header Authorization do cron diário.
     * Nunca deve ser definido em arquivo versionado.
     */
    'cron_secret' => env('CRON_SECRET'),

];
