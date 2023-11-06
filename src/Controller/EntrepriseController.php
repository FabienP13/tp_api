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
        // $entreprises = json_decode($response->getContent(), true);
        // // dd($entreprises["results"]);
        // dd(json_encode($entreprises["results"]));
        // // dd(json_encode($entreprises["results"]));
        // $datas = $serializer->deserialize(json_encode($entreprises["results"]), Entreprise::class, 'json');
        // dd($datas);
        $res = $response->toArray();
        $res['results'] = $serializer->denormalize($res['results'], Entreprise::class . '[]');
        dd($res['results']);
        return $res;
    }

    // public function search(string $q, int $page = 1): array
    // {

    //     $res = $res->toArray();
    //     $res['results'] = $this->serializer->denormalize($res['results'], Company::class . '[]');
    //     return $res;
    // }
}
