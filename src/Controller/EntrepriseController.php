<?php

namespace App\Controller;

use App\Services\SearchCompanyService;
use App\Services\FileService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Routing\Annotation\Route;
use App\Model\Entreprise;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class EntrepriseController extends AbstractController
{
    private const LOGIN = 'root';
    private const PASSWORD = 'root';
    private const filePath = "./entreprises/";
    public function __construct(
        private HttpClientInterface $client,
        private SearchCompanyService $searchCompanyService,
        private FileService $fileService,
        private ValidatorInterface  $validator
    ) {
    }

    /***************************
     ******** EXERCICE 1 *******
     ***************************/
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

    /***************************
     ******** EXERCICE 2 *******
     ***************************/
    #[Route('get-details-ursaff', name: 'get_details_ursaff')]
    public function getDetailsUrsaff(Request $request)
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

    /***************************
     ******** EXERCICE 3 *******
     ***************************/
    #[Route('api-ouverte-ent-liste', name: 'api_ouverte_ent_liste', methods: ['GET'])]
    public function getCompanyList(Request $request, NormalizerInterface $serializer): Response
    {
        $format = $request->headers->get('Accept');

        $files =  $this->fileService->findFiles('*.json');
        if (!$files) {
            return new Response("Aucune entreprise enregistrée", 200);
        }

        if ($request->getMethod() !== 'GET') {
            return new Response('La méthode ' . $request->getMethod() . ' n\'est pas autorisée.', 405);
        }

        if ($format == 'application/json') {

            foreach ($files as $file) {
                $datas = json_decode($file->getContents());
                $companies[] = $datas;
            }
            $response = new Response(json_encode($companies), 200);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        } else if ($format == 'application/csv') {

            $files =  $this->fileService->findFiles('*.csv');

            foreach ($files as $file) {
                $datas = $file->getContents();
                $companies[] = $datas;
            }

            $response = new Response(json_encode($companies), 200);
            $response->headers->set('Content-Type', 'application/csv');
            return $response;
        } else {
            return new Response('Format non pris en compte', 406);
        }
    }

    #[Route('api-ouverte-ent-liste-siren', name: 'api_ouverte_ent_liste_siren', methods: ['GET'])]
    public function getCompanyBySiren(Request $request): Response
    {
        if ($request->getMethod() !== 'GET') {
            return new Response('La méthode ' . $request->getMethod() . ' n\'est pas autorisée.', 405);
        }

        $siren = $request->query->get('siren'); //récuperation du param ?siren
        $company = $this->searchCompanyService->getCompanyInfos($siren); //vérification que la company existe

        if (!$company) { //Si elle n'existe pas => erreur 404
            return new Response("Aucune entreprise trouvée pour ce siren", 404);
        } else { // sinon je renvoie les données souhaitées au format JSON avec un code 200

            $companyInfos = [
                'raison sociale' => $company->getNomRaisonSociale(),
                'adresse' => $company->getSiege()["adresse"],
                'siret' => $company->getSiege()["siret"]
            ];

            $response = new Response(json_encode($companyInfos), 200);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
    }

    #[Route('api-ouverte-entreprise', name: 'create_company', methods: ['POST'])]
    public function createCompany(Request $request, NormalizerInterface $serializer): Response
    {
        $datas = $request->getContent();

        if ($request->getMethod() !== 'POST') {
            return new Response('La méthode ' . $request->getMethod()  . ' n\'est pas autorisée.', 405);
        }

        $newCompany = $serializer->deserialize($datas, Entreprise::class, 'json');
        $error = $this->validationDatas($newCompany);

        if ($error == null) {
            $siren = $newCompany->getSiren();
            $files = $this->fileService->findFiles("$siren.json");
            $fileExist = [];
            foreach ($files as $file) {
                $fileExist = $file->getContents();
            }

            if ($fileExist) {
                return new Response('L\'entreprise ' . $newCompany->getNomRaisonSociale()  . ' existe déjà.', 409);
            } else {
                $this->fileService->verifyingFile($newCompany->getSiren(), $newCompany);
                return new Response('L\'entreprise a bien été créée :  ' . $serializer->serialize($newCompany, 'json'), 201);
            }
        }
    }

    public function validationDatas($datas)
    {
        // Je fais la validation de mes asserts de mon Model Entreprise.php
        $errors = $this->validator->validate($datas);


        //Je cherche à valider les champs supplémentaires de mon objet 
        $errors->addAll(
            $this->validator->validate($datas->getSiege()['code_postale'], [
                new Assert\NotBlank([
                    'message' => 'La valeur du code_postale ne doit pas être vide'
                ]),
                new Assert\Regex([
                    'pattern' => '/^\d{5}$/',
                    'message' => 'Le Code postale doit contenir exactement 5 chiffres',
                ]),
            ])
        );
        $errors->addAll(
            $this->validator->validate($datas->getSiege()['ville'], new Assert\NotBlank([
                'message' => 'Vous devez renseigner une ville pour l\'entreprise',
            ]))
        );

        if (count($errors) > 0) {

            $messagesErrors = "";
            foreach ($errors as $error) {
                $message = $error->getMessage();
                $attributeError = $error->getPropertyPath();
                $messagesErrors .= "$attributeError : $message\n";
            }
            throw new BadRequestException($messagesErrors);
        }
    }
    /***************************
     ******** EXERCICE 4 *******
     ***************************/

    #[Route('api-protege', name: 'update_company', methods: ['PATCH'])]
    public function updateCompany(Request $request)
    {
        $isAuth = $this->isAuthenticated($request->headers);
        if ($isAuth === false) {
            return new Response('Vous n\'êtes pas authentifié.', 401);
        }

        if ($request->getMethod() !== 'PATCH') {
            return new Response('La méthode ' . $request->getMethod()  . ' n\'est pas autorisée.', 405);
        }

        //Récuparation des données 
        $datas = $request->getContent();
        $isJson = json_decode($datas, true);

        if (!$isJson) {
            return new Response('Format JSON invalide', 400);
        }

        $siren = $isJson["siren"];
        $files = $this->fileService->findFiles("$siren.json");
        $fileExist = [];
        foreach ($files as $file) {
            $fileExist = $file->getContents();
        }

        if (!$fileExist) {
            return new Response('Aucune entreprise trouvée avec ce siren.', 404);
        }

        //Récupération entreprise
        $contentFile = file_get_contents(self::filePath . $siren . '.json');
        $jsonData = json_decode($contentFile, true);

        $messageErreur = '';
        // Met à jour les champs avec les nouvelles valeurs
        foreach ($isJson as $key => $value) {
            if (array_key_exists($key, $jsonData)) {
                $jsonData[$key] = $value;
            } else {
                $messageErreur .= 'La propriété ' . $key . ' n\'existe pas' . " \n";
            }
        }
        if ($messageErreur) {
            return new Response($messageErreur, 400);
        }

        file_put_contents(self::filePath . $siren . '.json', json_encode($jsonData, JSON_PRETTY_PRINT));
        // Entreprise modifiée (HTTP 200) (possible message avec lien vers la nouvelle ressource)
        return new Response('Entreprise modifiée ! ', 201);
    }
    #[Route('api-protege', name: 'delete_company', methods: ['DELETE'])]
    public function deleteCompany(Request $request)
    {
        $isAuth = $this->isAuthenticated($request->headers);
        if ($isAuth === false) {
            return new Response('Vous n\'êtes pas authentifié.', 401);
        }

        if ($request->getMethod() !== 'DELETE') {
            return new Response('La méthode ' . $request->getMethod()  . ' n\'est pas autorisée.', 405);
        }

        //Récuparation des données 
        $datas = $request->getContent();
        $isJson = json_decode($datas, true);

        if (!$isJson) {
            return new Response('Format JSON invalide', 400);
        }
        $siren = $isJson["siren"];
        $files = $this->fileService->findFiles("$siren.json");
        $fileExist = [];
        foreach ($files as $file) {
            $fileExist = $file->getContents();
        }

        if (!$fileExist) {
            return new Response('Aucune entreprise trouvée avec ce siren.', 404);
        }

        $this->fileService->deleteFile($siren);
        return new Response('L\'Entreprise a bien été supprimée', 201);
    }

    private function isAuthenticated($headers)
    {

        //Récupération du login/password serveur
        $login = self::LOGIN;
        $password = self::PASSWORD;
        $tokenServeur = base64_encode("$login:$password");

        //Vérification présence Authorization
        if (!$headers->get('Authorization')) {
            return false;
        }

        $tokenClient = explode(' ', $headers->get('Authorization'));

        if ($tokenClient[0] != 'Basic' || $tokenClient[1] != $tokenServeur) {
            return false;
        }
    }
}
