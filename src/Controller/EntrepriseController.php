<?php

namespace App\Controller;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Model\Entreprise;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EntrepriseController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    #[Route('/entreprise', name: 'app_entreprise')]
    public function index(): Response
    {
        return $this->render('entreprise/index.html.twig', [
            'controller_name' => 'EntrepriseController',
        ]);
    }

    #[Route('recherche-entreprise', name: 'recherche_entreprise')]
    public function recherche(Request $request, NormalizerInterface $serializer)
    {
        $entreprise = $request->request->get('entreprise');
        $response = $this->client->request('GET', 'https://recherche-entreprises.api.gouv.fr/search', [
            // these values are automatically encoded before including them in the URL
            'query' => [
                'q' => $entreprise
            ],
        ]);

        $res = $response->toArray();
        $res['results'] = $serializer->denormalize($res['results'], Entreprise::class . '[]');
        // dd($res['results']);
        return $this->render('entreprise/show.html.twig', [
            'entreprises' => $res['results'],
        ]);
    }
}
// 