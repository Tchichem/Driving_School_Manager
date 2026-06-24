<?php
namespace App\Controller;
use App\Entity\ELEVE;
use App\Form\EleveType;
use App\Repository\ELEVERepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EleveController extends AbstractController
{
    #[Route('/', name: 'app_eleves')]
    public function index(
        ELEVERepository $eleveRepository,
        PaginatorInterface $paginator,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('tri', 'id_eleve');
        $direction = $request->query->get('sens', 'asc');
        $allowedSorts = ['id_eleve', 'nom_eleve', 'prenom_eleve', 'date_naissance_eleve', 'date_inscription'];
        if (!in_array($sort, $allowedSorts)) $sort = 'id_eleve';
        if (!in_array($direction, ['asc', 'desc'])) $direction = 'asc';
        $sortField = 'e.' . $sort;
        $query = $eleveRepository->createQueryBuilder('e')
            ->where('e.nom_eleve LIKE :search OR e.prenom_eleve LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy($sortField, $direction)
            ->getQuery();
        $eleves = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10,
            ['sortFieldAllowList' => ['e.id_eleve', 'e.nom_eleve', 'e.prenom_eleve', 'e.date_naissance_eleve', 'e.date_inscription']]
        );

        $form = null;
        $eleve = new ELEVE();
        $form = $this->createForm(EleveType::class, $eleve);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $maxId = $eleveRepository->createQueryBuilder('e')
                ->select('MAX(e.id_eleve)')
                ->getQuery()
                ->getSingleScalarResult();
            $eleve->setIdEleve(($maxId ?? 0) + 1);
            $eleve->setDateInscription(new \DateTime());
            $em->persist($eleve);
            $em->flush();
            return $this->redirectToRoute('app_eleves');
        }

        return $this->render('eleve/index.html.twig', [
            'eleves' => $eleves,
            'form' => $form,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
    }

    #[Route('/eleves/update/{id}', name: 'app_eleves_update', methods: ['POST'])]
    public function update(int $id, Request $request, ELEVERepository $eleveRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('update_eleve', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $eleve = $eleveRepository->find($id);
        if (!$eleve) {
            throw $this->createNotFoundException('Élève introuvable.');
        }
        $champ = $request->request->get('champ');
        $valeur = (bool) $request->request->get('valeur');
        if ($champ === 'code') {
            $eleve->setCode($valeur);
        } elseif ($champ === 'conduite') {
            $eleve->setConduite($valeur);
        }
        $em->flush();
        return $this->redirectToRoute('app_eleves');
    }

    #[Route('/eleves/{id}/edit', name: 'app_eleves_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request, ELEVERepository $eleveRepository, EntityManagerInterface $em): Response
    {
        $eleve = $eleveRepository->find($id);

        if (!$eleve) {
            throw $this->createNotFoundException('Élève introuvable.');
        }

        $form = $this->createForm(EleveType::class, $eleve);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Élève modifié avec succès.');
            return $this->redirectToRoute('app_eleves');
        }

        return $this->render('eleve/edit.html.twig', [
            'form' => $form,
            'eleve' => $eleve,
        ]);
    }

    #[Route('/eleves/{id}/delete', name: 'app_eleves_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, Request $request, ELEVERepository $eleveRepository, EntityManagerInterface $em): Response
    {
        $eleve = $eleveRepository->find($id);

        if (!$eleve) {
            throw $this->createNotFoundException('Élève introuvable.');
        }

        if ($this->isCsrfTokenValid('delete_eleve_' . $id, $request->request->get('_token'))) {
            $em->remove($eleve);
            $em->flush();
            $this->addFlash('success', 'Élève supprimé avec succès.');
        }

        return $this->redirectToRoute('app_eleves');
    }
}