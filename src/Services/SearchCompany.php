<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class SearchCompany
{
    private string $API_URL = 'https://recherche-entreprises.api.gouv.fr/search';
    private string $URSAFF_API = 'https://mon-entreprise.urssaf.fr/api/v1/evaluate';

    public function __construct(
        private HttpClientInterface $client
    ) {
    }

    public function getCompanyInfos(string $siren): mixed
    {
        $response = $this->client->request('GET', "$this->API_URL?q=$siren");

        // return json_decode($response->getContent());
        return $response->toArray();
    }

    public function getUrsaffInfos(string $salaire, string $contrat): mixed
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
        $content = $response->toArray();
        return $content;
    }
}
