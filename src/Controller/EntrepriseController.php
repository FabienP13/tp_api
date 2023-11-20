<?php

namespace App\Controller;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Model\Entreprise;
use Symfony\Component\Filesystem\Filesystem;
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
    public function recherche(Request $request, NormalizerInterface $serializer, $page = 1)
    {
        $entreprise = $request->request->get('entreprise');
        $page = $request->request->get('page');
        if ($page == null) {
            $page = 1;
        }
        $response = $this->client->request('GET', 'https://recherche-entreprises.api.gouv.fr/search', [
            // these values are automatically encoded before including them in the URL
            'query' => [
                'q' => $entreprise,
                'page' => $page
            ],
        ]);

        $res = $response->toArray();
        $res['results'] = $serializer->denormalize($res['results'], Entreprise::class . '[]');

        return $this->render('entreprise/show.html.twig', [
            'entreprises' => $res['results'],
            'search' => $entreprise,
            'page' => $page,
        ]);
    }
    #[Route('show-entreprise', name: 'show_entreprise')]
    public function show(Request $request, NormalizerInterface $serializer, Filesystem $filesystem)
    {
        $data = $request->query->all();
        $siren = $data["siren"];
        $response = $this->client->request('GET', 'https://recherche-entreprises.api.gouv.fr/search', [
            // these values are automatically encoded before including them in the URL
            'query' => [
                'q' => $siren,
            ],
        ]);
        $result = $response->toArray();
        $entreprise = $serializer->denormalize($result['results'], Entreprise::class . '[]');

        //sérialiser les données dans un format précis - JSON et CSV
        $json = $serializer->serialize($entreprise[0], 'json');
        $csv = $serializer->serialize($entreprise[0], 'csv', ['csv_delimiter' => ';']);

        //Chemin où enregistrer les fichiers 
        $filePathCompagnyJson = './entreprises/' . $result["results"][0]["siren"] . '.json';
        $filePathCompagnyCsv = './entreprises/' . $result["results"][0]["siren"] . '.csv';
        $filePath = './siren/entreprises.txt';

        // $data = json_encode($result["results"][0]);
        var_dump($filePathCompagnyJson);
        //enregistrer les données dans un fichier 
        try {
            if ($filesystem->exists($filePathCompagnyJson)) {
                echo 'le fichier existe déjà, on fait rien';
                // $filesystem->appendToFile($filePath, $data);
            } elseif ($filesystem->exists($filePath)) {
                echo 'si le fichier entreprise.txt, on ajoute le n° siren dedans';
                $filesystem->appendToFile($filePath, $result["results"][0]["siren"] . '-elseif');
            } else {
                echo 'ici';
                echo $filePathCompagnyJson . '<br>';
                var_dump($json);
                $filesystem->dumpFile($filePath, $result["results"][0]["siren"] . '-else');
                $filesystem->dumpFile($filePathCompagnyCsv, $csv);
                $filesystem->dumpFile($filePathCompagnyJson, $json);
            }
            $message = 'data saved in the file ';
            // Le fichier a été enregistré avec succès.
        } catch (\Exception $e) {
            // Une erreur s'est produite lors de l'enregistrement du fichier.
            $message = $e;
        }
        return $this->render('entreprise/details.html.twig', [
            'entreprise' => $entreprise[0],
            'message' => $message,
        ]);
    }
}
// 