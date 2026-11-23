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
     * Lista explícita de IPs autorizados a enviar X-Forwarded-*. Vazio = confiar
     * em ninguém, e o IP do cliente é o da conexão real. `*` já foi o padrão e
     * permitia que qualquer visitante forjasse o próprio IP para burlar o
     * throttle de login; agora a configuração falha em vez de aceitar curinga.
     */
    'trusted_proxies' => (static function (): array {
        $proxies = array_values(array_filter(
            array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))
        ));

        foreach ($proxies as $proxy) {
            if ($proxy === '*' || $proxy === '**') {
                throw new RuntimeException(
                    'TRUSTED_PROXIES=* não é permitido: informe apenas os IPs dos proxies da sua infraestrutura.'
                );
            }
        }

        return $proxies;
    })(),

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
