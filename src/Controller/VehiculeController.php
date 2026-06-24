<?php
namespace App\Controller;

use App\Entity\VEHICULE;
use App\Entity\MODELE;
use App\Form\VehiculeType;
use App\Form\ModeleType;
use App\Repository\VEHICULERepository;
use App\Repository\MODELERepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class VehiculeController extends AbstractController
{
    #[Route('/vehicules', name: 'app_vehicules')]
    public function index(
        VEHICULERepository $vehiculeRepository,
        MODELERepository $modeleRepository,
        PaginatorInterface $paginator,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Search & sort vehicules
        $searchVehicule = $request->query->get('search_vehicule', '');
        $sortVehicule = $request->query->get('tri_vehicule', 'num_immatric');
        $directionVehicule = $request->query->get('sens_vehicule', 'asc');

        $allowedSortsVehicule = ['num_immatric'];
        if (!in_array($sortVehicule, $allowedSortsVehicule)) $sortVehicule = 'num_immatric';
        if (!in_array($directionVehicule, ['asc', 'desc'])) $directionVehicule = 'asc';

        $queryVehicule = $vehiculeRepository->createQueryBuilder('v')
            ->leftJoin('v.modele_vehic', 'm')
            ->addSelect('m')
            ->where('v.num_immatric LIKE :search')
            ->setParameter('search', '%' . $searchVehicule . '%')
            ->orderBy('v.' . $sortVehicule, $directionVehicule)
            ->getQuery();

        $vehicules = $paginator->paginate(
            $queryVehicule,
            $request->query->getInt('page_vehicule', 1),
            10,
            [
                'pageParameterName' => 'page_vehicule',
                'sortFieldAllowList' => ['v.num_immatric', 'v.etat'],
            ]
        );

        // Search & sort modeles
        $searchModele = $request->query->get('search_modele', '');
        $sortModele = $request->query->get('tri_modele', 'modele_vehic');
        $directionModele = $request->query->get('sens_modele', 'asc');

        $allowedSortsModele = ['modele_vehic', 'marque', 'annee', 'date_achat'];
        if (!in_array($sortModele, $allowedSortsModele)) $sortModele = 'modele_vehic';
        if (!in_array($directionModele, ['asc', 'desc'])) $directionModele = 'asc';

        $queryModele = $modeleRepository->createQueryBuilder('m')
            ->where('m.modele_vehic LIKE :search OR m.marque LIKE :search')
            ->setParameter('search', '%' . $searchModele . '%')
            ->orderBy('m.' . $sortModele, $directionModele)
            ->getQuery();

        $modeles = $paginator->paginate(
            $queryModele,
            $request->query->getInt('page_modele', 1),
            10,
            [
                'pageParameterName' => 'page_modele',
                'sortFieldAllowList' => ['m.modele_vehic', 'm.marque', 'm.annee', 'm.date_achat'],
            ]
        );

        // Vehicule form
        $vehicule = new VEHICULE();
        $formVehicule = $this->createForm(VehiculeType::class, $vehicule);
        $formVehicule->handleRequest($request);
        if ($formVehicule->isSubmitted() && $formVehicule->isValid()) {
            $em->persist($vehicule);
            $em->flush();
            return $this->redirectToRoute('app_vehicules');
        }

        // Modele form
        $modele = new MODELE();
        $formModele = $this->createForm(ModeleType::class, $modele);
        $formModele->handleRequest($request);
        if ($formModele->isSubmitted() && $formModele->isValid()) {
            $em->persist($modele);
            $em->flush();
            return $this->redirectToRoute('app_vehicules');
        }

        return $this->render('vehicule/index.html.twig', [
            'vehicules' => $vehicules,
            'modeles' => $modeles,
            'formVehicule' => $formVehicule,
            'formModele' => $formModele,
            'sortVehicule' => $sortVehicule,
            'directionVehicule' => $directionVehicule,
            'searchVehicule' => $searchVehicule,
            'sortModele' => $sortModele,
            'directionModele' => $directionModele,
            'searchModele' => $searchModele,
        ]);
    }

    #[Route('/vehicules/update/{id}', name: 'app_vehicules_update', methods: ['POST'])]
    public function update(string $id, Request $request, VEHICULERepository $vehiculeRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('update_vehicule', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    
        $vehicule = $vehiculeRepository->find($id);
        if (!$vehicule) {
            throw $this->createNotFoundException('Véhicule introuvable.');
        }
        $vehicule->setEtat((bool) $request->request->get('valeur'));
        $em->flush();
        return $this->redirectToRoute('app_vehicules');
    }

    #[Route('/vehicules/{id}/edit', name: 'app_vehicules_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editVehicule(string $id, Request $request, VEHICULERepository $vehiculeRepository, EntityManagerInterface $em): Response
    {
        $vehicule = $vehiculeRepository->find($id);
        if (!$vehicule) throw $this->createNotFoundException('Véhicule introuvable.');
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Véhicule modifié avec succès.');
            return $this->redirectToRoute('app_vehicules');
        }
        return $this->render('vehicule/edit_vehicule.html.twig', ['form' => $form, 'vehicule' => $vehicule]);
    }

    #[Route('/vehicules/{id}/delete', name: 'app_vehicules_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteVehicule(string $id, Request $request, VEHICULERepository $vehiculeRepository, EntityManagerInterface $em): Response
    {
        $vehicule = $vehiculeRepository->find($id);
        if (!$vehicule) throw $this->createNotFoundException('Véhicule introuvable.');
        if ($this->isCsrfTokenValid('delete_vehicule_' . $id, $request->request->get('_token'))) {
            $em->remove($vehicule);
            $em->flush();
            $this->addFlash('success', 'Véhicule supprimé avec succès.');
        }
        return $this->redirectToRoute('app_vehicules');
    }

    #[Route('/modeles/{id}/edit', name: 'app_modeles_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editModele(string $id, Request $request, MODELERepository $modeleRepository, EntityManagerInterface $em): Response
    {
        $modele = $modeleRepository->find($id);
        if (!$modele) throw $this->createNotFoundException('Modèle introuvable.');
        $form = $this->createForm(ModeleType::class, $modele);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Modèle modifié avec succès.');
            return $this->redirectToRoute('app_vehicules');
        }
        return $this->render('vehicule/edit_modele.html.twig', ['form' => $form, 'modele' => $modele]);
    }

    #[Route('/modeles/{id}/delete', name: 'app_modeles_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteModele(string $id, Request $request, MODELERepository $modeleRepository, EntityManagerInterface $em): Response
    {
        $modele = $modeleRepository->find($id);
        if (!$modele) throw $this->createNotFoundException('Modèle introuvable.');
        if ($this->isCsrfTokenValid('delete_modele_' . $id, $request->request->get('_token'))) {
            $em->remove($modele);
            $em->flush();
            $this->addFlash('success', 'Modèle supprimé avec succès.');
        }
        return $this->redirectToRoute('app_vehicules');
    }
}