<?php

namespace App\Support;

/**
 * Distância sobre a superfície da Terra em metros, pela fórmula de haversine.
 *
 * A conta é feita no servidor, nunca no navegador: a distância é a resposta à
 * pergunta "o técnico esteve no local?", e uma resposta calculada no aparelho de
 * quem quer provar que esteve não é resposta. O projeto não depende de biblioteca
 * de geo — 400 metros de um endereço de assistência técnica não pedem pacote de
 * projeção cartográfica.
 *
 * Coordenada que falta devolve null, e null não vira zero: visita sem GPS é visita
 * sem medida, não visita no portão.
 */
class Distancia
{
    private const RAIO_DA_TERRA_METROS = 6_371_000.0;

    public static function metros(mixed $latitudeUm, mixed $longitudeUm, mixed $latitudeDois, mixed $longitudeDois): ?float
    {
        $latUm = static::grau($latitudeUm);
        $lonUm = static::grau($longitudeUm);
        $latDois = static::grau($latitudeDois);
        $lonDois = static::grau($longitudeDois);

        if ($latUm === null || $lonUm === null || $latDois === null || $lonDois === null) {
            return null;
        }

        $paraRadiano = M_PI / 180.0;
        $deltaLat = ($latDois - $latUm) * $paraRadiano;
        $deltaLon = ($lonDois - $lonUm) * $paraRadiano;

        $metade = sin($deltaLat / 2) ** 2
            + cos($latUm * $paraRadiano) * cos($latDois * $paraRadiano) * sin($deltaLon / 2) ** 2;

        return round(self::RAIO_DA_TERRA_METROS * 2 * atan2(sqrt($metade), sqrt(1 - $metade)), 2);
    }

    /**
     * O `decimal:7` do Eloquent devolve string. Aceitar só float obrigaria cada
     * chamada a saber de onde o número veio, e o ponto aqui é a conta, não a origem.
     */
    private static function grau(mixed $valor): ?float
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return null;
        }

        return (float) $valor;
    }
}
