<?php

namespace App\Controller;

use App\Services\SearchCompany;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
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
        private SearchCompany $searchCompany,
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
        $company = $this->searchCompany->getCompanyInfos($siren, $serializer);
        $entreprise = $serializer->denormalize($company["results"], Entreprise::class . '[]');

        //sérialiser les données dans un format précis - JSON et CSV
        $json = $serializer->serialize($entreprise[0], 'json');
        $csv = $serializer->serialize($entreprise[0], 'csv', ['csv_delimiter' => ';']);

        //Chemin où enregistrer les fichiers 
        $filePathCompagnyJson = './entreprises/' . $company["results"][0]["siren"] . '.json';
        $filePathCompagnyCsv = './entreprises/' . $company["results"][0]["siren"] . '.csv';
        $filePath = './siren/entreprises.txt';

        // $data = json_encode($result["results"][0]);
        // var_dump($filePathCompagnyJson);
        //enregistrer les données dans un fichier 
        try {
            if ($filesystem->exists($filePathCompagnyJson)) { // Si le fichier json/csv existe 
                // $filesystem->appendToFile($filePath, $data);
            }
            //si le fichier entreprise.txt existe ET que le fichier json/csv n'existe pas
            elseif ($filesystem->exists($filePath) && !$filesystem->exists($filePathCompagnyJson)) {
                $filesystem->appendToFile($filePath, $company["results"][0]["siren"] . '-');
                $filesystem->dumpFile($filePathCompagnyCsv, $csv);
                $filesystem->dumpFile($filePathCompagnyJson, $json);
            } else {
                $filesystem->dumpFile($filePath, $company["results"][0]["siren"] . '-');
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

    #[Route('get-details-ursaff', name: 'get_details_ursaff')]
    public function getDetailsUrsaff(Request $request, NormalizerInterface $serializer,)
    {
        //Récupération des params
        $data = $request->query->all();
        $salaire = $data["salaire"];
        $entreprise = $data["entreprise"];

        //On récupère les données de la company
        $company = $this->searchCompany->getCompanyInfos($entreprise);
        $entreprise = $serializer->denormalize($company["results"], Entreprise::class . '[]');
        $typeContrat = ['CDI', 'stage', 'alternance', 'CDD'];
        // foreach ($typeContrat as $contrat) {
        //     $contratType = strtolower($contrat);
        //     $contratType = $this->searchCompany->getUrsaffInfos($salaire, $typeContrat[0])
        // }
        // dd('test');
        $cdi = $this->searchCompany->getUrsaffInfos($salaire, $typeContrat[0]);
        $stage = $this->searchCompany->getUrsaffInfos($salaire, $typeContrat[1]);
        $alternance = $this->searchCompany->getUrsaffInfos($salaire, $typeContrat[2]);
        $cdd = $this->searchCompany->getUrsaffInfos($salaire, $typeContrat[3]);

        return $this->render('entreprise/details.html.twig', [
            'entreprise' => $entreprise[0],
            'cdi' => $cdi,
            'stage' => $stage,
            'alternance' => $alternance,
            'cdd' => $cdd,
        ]);
    }
}
// 