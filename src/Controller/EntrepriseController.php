<?php

namespace App\Controller;

use App\Services\SearchCompanyService;
use App\Services\FileService;
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
        private SearchCompanyService $searchCompanyService,
        private FileService $fileService,
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
            'query' => [
                'q' => $entreprise,
                'page' => $page
            ],
        ]);

        $res = $response->toArray();
        $res["results"] = $serializer->denormalize($res['results'], Entreprise::class . '[]');

        return $this->render('entreprise/show.html.twig', [
            'entreprises' => $res['results'],
            'search' => $entreprise,
            'page' => $page,
        ]);
    }
    #[Route('show-entreprise', name: 'show_entreprise')]
    public function show(Request $request, NormalizerInterface $serializer)
    {
        $data = $request->query->all();
        $siren = $data["siren"];
        $company = $this->searchCompanyService->getCompanyInfos($siren, $serializer);

        //Vérifier si les fichiers existent 
        $this->fileService->verifyingFile($company->getSiren(), $company);

        return $this->render('entreprise/details.html.twig', [
            'entreprise' => $company,
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
        $company = $this->searchCompanyService->getCompanyInfos($entreprise);
        $typeContrat = ['CDI', 'stage', 'alternance', 'CDD'];

        $cdi = $this->searchCompanyService->getUrsaffInfos($salaire, $typeContrat[0]);
        $stage = $this->searchCompanyService->getUrsaffInfos($salaire, $typeContrat[1]);
        $alternance = $this->searchCompanyService->getUrsaffInfos($salaire, $typeContrat[2]);
        $cdd = $this->searchCompanyService->getUrsaffInfos($salaire, $typeContrat[3]);

        return $this->render('entreprise/details.html.twig', [
            'entreprise' => $company,
            'cdi' => $cdi,
            'stage' => $stage,
            'alternance' => $alternance,
            'cdd' => $cdd,
        ]);
    }

    #[Route('api-ouverte-ent-liste', name: 'api_ouverte_ent_liste', methods: ['GET'])]
    public function getCompanyList(Request $request)
    {
    }
}
// 