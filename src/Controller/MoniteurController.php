<?php

namespace App\Controller;

use App\Entity\MONITEUR;
use App\Form\MoniteurType;
use App\Repository\MONITEURRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MoniteurController extends AbstractController
{
    #[Route('/moniteurs', name: 'app_moniteurs')]
    public function index(
        MONITEURRepository $moniteurRepository,
        PaginatorInterface $paginator,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Pagination
        $search = $request->query->get('search', '');
        $sort = $request->query->get('tri', 'id_moniteur');
        $direction = $request->query->get('sens', 'asc');

        $allowedSorts = ['id_moniteur', 'nom_moniteur', 'prenom_moniteur', 'date_naissance_moniteur', 'date_embauche'];
        if (!in_array($sort, $allowedSorts)) $sort = 'id_moniteur';
        if (!in_array($direction, ['asc', 'desc'])) $direction = 'asc';

        $query = $moniteurRepository->createQueryBuilder('m')
            ->where('m.nom_moniteur LIKE :search OR m.prenom_moniteur LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('m.' . $sort, $direction)
            ->getQuery();

        $moniteurs = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10,
            ['sortFieldAllowList' => ['m.id_moniteur', 'm.nom_moniteur', 'm.prenom_moniteur', 'm.date_naissance_moniteur', 'm.date_embauche']]
        );

        // Create form
        $moniteur = new MONITEUR();
        $form = $this->createForm(MoniteurType::class, $moniteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manual increment ID
            $maxId = $moniteurRepository->createQueryBuilder('m')
                ->select('MAX(m.id_moniteur)')
                ->getQuery()
                ->getSingleScalarResult();
            $moniteur->setIdMoniteur(($maxId ?? 0) + 1);

            $em->persist($moniteur);
            $em->flush();

            return $this->redirectToRoute('app_moniteurs');
        }
        
        return $this->render('moniteur/index.html.twig', [
            'moniteurs' => $moniteurs,
            'form' => $form,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
    }

    #[Route('/moniteurs/update/{id}', name: 'app_moniteurs_update', methods: ['POST'])]
    public function update(int $id, Request $request, MONITEURRepository $moniteurRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('update_moniteur', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $moniteur = $moniteurRepository->find($id);
        if (!$moniteur) {
            throw $this->createNotFoundException('Moniteur introuvable.');
        }
        $moniteur->setActivite((bool) $request->request->get('valeur'));
        $em->flush();
        return $this->redirectToRoute('app_moniteurs');
    }

    #[Route('/moniteurs/{id}/edit', name: 'app_moniteurs_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request, MONITEURRepository $moniteurRepository, EntityManagerInterface $em): Response
    {
        $moniteur = $moniteurRepository->find($id);
        if (!$moniteur) {
            throw $this->createNotFoundException('Moniteur introuvable.');
        }
        $form = $this->createForm(MoniteurType::class, $moniteur);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Moniteur modifié avec succès.');
            return $this->redirectToRoute('app_moniteurs');
        }
        return $this->render('moniteur/edit.html.twig', [
            'form' => $form,
            'moniteur' => $moniteur,
        ]);
    }

    #[Route('/moniteurs/{id}/delete', name: 'app_moniteurs_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, Request $request, MONITEURRepository $moniteurRepository, EntityManagerInterface $em): Response
    {
        $moniteur = $moniteurRepository->find($id);
        if (!$moniteur) {
            throw $this->createNotFoundException('Moniteur introuvable.');
        }
        if ($this->isCsrfTokenValid('delete_moniteur_' . $id, $request->request->get('_token'))) {
            $em->remove($moniteur);
            $em->flush();
            $this->addFlash('success', 'Moniteur supprimé avec succès.');
        }
        return $this->redirectToRoute('app_moniteurs');
    }
}
