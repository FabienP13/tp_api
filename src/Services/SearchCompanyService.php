<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use App\Model\Entreprise;

class SearchCompanyService
{
    private string $API_URL = 'https://recherche-entreprises.api.gouv.fr/search';
    private string $URSAFF_API = 'https://mon-entreprise.urssaf.fr/api/v1/evaluate';

    public function __construct(
        private HttpClientInterface $client,
        private NormalizerInterface $serializer
    ) {
        $this->serializer = $serializer;
    }

    public function getCompanyInfos(string $siren): mixed
    {
        $response = $this->client->request('GET', "$this->API_URL?q=$siren");
        $res = json_decode($response->getContent());
        $entreprise = $this->serializer->deserialize(json_encode($res->results[0]), Entreprise::class, 'json');

        return $entreprise;
    }

    public function getUrsaffInfos(string $salaire, string $contrat): mixed
    {
        $expressions = $this->getExpressionsByContract($contrat);
        $data = [
            'situation' => [
                'salarié . contrat . salaire brut' => [
                    'valeur' => $salaire,
                    'unité' => '€ / mois',
                ],
                'salarié . contrat' => '"' . $contrat . '"',
            ],
            'expressions' => $expressions,
        ];
        $client = HttpClient::create();
        $response = $client->request('POST', $this->URSAFF_API, [
            'json' => $data,
        ]);

        $content = $response->getContent();
        return json_decode($content);
    }

    public function getExpressionsByContract($contrat)
    {
        switch ($contrat) {
            case 'CDI':
                $expressions = [
                    'salarié . rémunération . net . à payer avant impôt',
                    'salarié . coût total employeur',
                    'salarié . cotisations . salarié',
                ];
                break;
            case 'stage':
                $expressions = [
                    'salarié . contrat . stage . gratification minimale',
                ];
                break;
            case 'CDD':
                $expressions = [
                    'salarié . rémunération . net . à payer avant impôt',
                    'salarié . coût total employeur',
                    'salarié . cotisations . salarié',
                    'salarié . rémunération . indemnités CDD . fin de contrat',
                ];
                break;
            case 'alternance':
                $expressions = [
                    'salarié . rémunération . net . à payer avant impôt',
                    'salarié . coût total employeur',
                    'salarié . cotisations . salarié',
                ];
                break;
        }
        return $expressions;
    }
}
