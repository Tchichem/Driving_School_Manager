<?php
namespace App\Controller;
use App\Entity\LECON;
use App\Entity\CALENDRIER;
use App\Form\LeconType;
use App\Repository\LECONRepository;
use App\Repository\CALENDRIERRepository;
use App\Repository\ELEVERepository;
use App\Repository\MONITEURRepository;
use App\Repository\MODELERepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LeconController extends AbstractController
{
    #[Route('/lecons', name: 'app_lecons')]
    public function index(
        LECONRepository $leconRepository,
        CALENDRIERRepository $calendrierRepository,
        ELEVERepository $eleveRepository,
        MONITEURRepository $moniteurRepository,
        MODELERepository $modeleRepository,
        PaginatorInterface $paginator,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('tri', 'leconDateHeureRaw');
        $direction = $request->query->get('sens', 'asc');

        $allowedSorts = ['leconDateHeureRaw', 'duree'];
        if (!in_array($sort, $allowedSorts)) $sort = 'leconDateHeureRaw';
        if (!in_array($direction, ['asc', 'desc'])) $direction = 'asc';

        $query = $leconRepository->createQueryBuilder('l')
            ->join('l.lecon_eleve_id', 'e')
            ->join('l.lecon_moniteur_id', 'm')
            ->join('l.lecon_modele_vehic', 'v')
            ->where('e.nom_eleve LIKE :search OR m.nom_moniteur LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('l.' . $sort, $direction)
            ->getQuery();

        $lecons = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10,
            ['sortFieldAllowList' => ['l.leconDateHeureRaw', 'l.duree']]
        );

        // Lists for modal
        $eleves = $eleveRepository->findAll();
        $moniteurs = $moniteurRepository->findBy(['activite' => true]);
        $modeles = $modeleRepository->findAll();

        $lecon = new LECON();
        $form = $this->createForm(LeconType::class, $lecon);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $lecon->setLeconDateHeureRaw(
                $lecon->getLeconDateHeure()->getDateHeure()->format('Y-m-d H:i:s')
            );
            $em->persist($lecon);
            $em->flush();
            return $this->redirectToRoute('app_lecons');
        }

        return $this->render('lecon/index.html.twig', [
            'lecons' => $lecons,
            'form' => $form,
            'eleves' => $eleves,
            'moniteurs' => $moniteurs,
            'modeles' => $modeles,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search
        ]);
    }

    #[Route('/lecons/create', name: 'app_lecons_create', methods: ['POST'])]
    public function create(
        Request $request,
        ELEVERepository $eleveRepository,
        MONITEURRepository $moniteurRepository,
        MODELERepository $modeleRepository,
        EntityManagerInterface $em
    ): Response {
        // Verify CSRF 
        if (!$this->isCsrfTokenValid('create_lecon', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        $dateHeure = $request->request->get('date_heure_display') . ':00';
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s', $dateHeure);
        $eleveId = (int) $request->request->get('eleve_id');
        $moniteurId = (int) $request->request->get('moniteur_id');
        $modeleId = htmlspecialchars(strip_tags($request->request->get('modele_id')));
        $duree = $request->request->get('duree');

        // Validate data
        if (!$dt) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        if (!in_array((int)$duree, [30, 60, 90, 120])) {
            $this->addFlash('error', 'Durée invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        if (!$eleveRepository->find($eleveId)) {
            $this->addFlash('error', 'Élève invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        if (!$moniteurRepository->find($moniteurId)) {
            $this->addFlash('error', 'Moniteur invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        if (!$modeleRepository->find($modeleId)) {
            $this->addFlash('error', 'Véhicule invalide.');
            return $this->redirectToRoute('app_lecons');
        }

        $conn = $em->getConnection();
        $dateFormatted = $dt->format('Y-m-d H:i:s');
        $dureeInt = (int) $duree;

        $conn->executeStatement("SET DATEFORMAT ymd");

        // Verify if already exists
        $existing = $conn->fetchOne(
            "SELECT COUNT(*) FROM LECON 
            WHERE lecon_moniteur_id = '$moniteurId'
            AND lecon_date_heure < DATEADD(minute, $dureeInt, '$dateFormatted')
            AND DATEADD(minute, duree, lecon_date_heure) > '$dateFormatted'"
        );
        if ($existing > 0) {
            $this->addFlash('error', 'Ce moniteur est déjà réservé sur ce créneau.');
            return $this->redirectToRoute('app_lecons');
        }

        $existingEleve = $conn->fetchOne(
            "SELECT COUNT(*) FROM LECON 
            WHERE lecon_eleve_id = '$eleveId'
            AND lecon_date_heure < DATEADD(minute, $dureeInt, '$dateFormatted')
            AND DATEADD(minute, duree, lecon_date_heure) > '$dateFormatted'"
        );
        if ($existingEleve > 0) {
            $this->addFlash('error', 'Cet élève a déjà un rendez-vous sur ce créneau.');
            return $this->redirectToRoute('app_lecons');
        }

        $existingVehicule = $conn->fetchOne(
            "SELECT COUNT(*) FROM LECON 
            WHERE lecon_modele_vehic = '$modeleId'
            AND lecon_date_heure < DATEADD(minute, $dureeInt, '$dateFormatted')
            AND DATEADD(minute, duree, lecon_date_heure) > '$dateFormatted'"
        );
        if ($existingVehicule > 0) {
            $this->addFlash('error', 'Ce véhicule est déjà réservé sur ce créneau.');
            return $this->redirectToRoute('app_lecons');
        }

        // Insert in transaction
        $conn->beginTransaction();
        try {
            $conn->executeStatement(
                "INSERT INTO CALENDRIER (date_heure) VALUES ('$dateFormatted')"
            );
            $calendrierId = $conn->lastInsertId();

            $conn->executeStatement(
                "INSERT INTO LECON (lecon_date_heure, lecon_eleve_id, lecon_moniteur_id, lecon_modele_vehic, duree, calendrier_id) 
                VALUES ('$dateFormatted', '$eleveId', '$moniteurId', '$modeleId', $duree, $calendrierId)"
            );

            $conn->commit();
            return $this->redirectToRoute('app_lecons');
        } catch (\Exception $e) {
            $conn->rollBack();
            $this->addFlash('error', 'Une erreur est survenue lors de la réservation.');
            return $this->redirectToRoute('app_lecons');
        }
    }

    #[Route('/lecons/{id}/delete', name: 'app_lecons_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id, Request $request, LECONRepository $leconRepository, EntityManagerInterface $em): Response
    {
        $lecon = $leconRepository->find($id);

        if (!$lecon) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        if ($this->isCsrfTokenValid('delete_lecon_' . $id, $request->request->get('_token'))) {
            $em->remove($lecon);
            $em->flush();
            $this->addFlash('success', 'Rendez-vous supprimé avec succès.');
        }

        return $this->redirectToRoute('app_lecons');
    }

    #[Route('/lecons/events', name: 'app_lecons_events', methods: ['GET'])]
    public function events(LECONRepository $leconRepository): JsonResponse
    {
        $lecons = $leconRepository->findAll();
        $events = [];
        foreach ($lecons as $lecon) {
            $dateHeure = $lecon->getLeconDateHeureRaw();
            if (!$dateHeure) continue;
            $start = \DateTime::createFromFormat('Y-m-d H:i:s.v', $dateHeure);
            if (!$start) {
                $start = \DateTime::createFromFormat('Y-m-d H:i:s', $dateHeure);
            }
            $end = clone $start;
            $end->modify('+' . $lecon->getDuree() . ' minutes');
            $events[] = [
                'id' => $lecon->getIdLecon(),
                'title' => $lecon->getLeconEleveId()->getNomEleve() . ' - ' . $lecon->getLeconMoniteurId()->getNomMoniteur(),
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
            ];
        }
        return new JsonResponse($events);
    }
}