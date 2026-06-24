<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/admin/users', name: 'app_users')]
    public function index(Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $users = $userRepository->findAll();

        if ($request->isMethod('POST')) {
            // Verify CSRF token
            if (!$this->isCsrfTokenValid('create_user', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $role = $request->request->get('role');

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Email invalide.');
                return $this->redirectToRoute('app_users');
            }

            // Validate password length
            if (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');
                return $this->redirectToRoute('app_users');
            }

            // Validate role
            if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN'])) {
                $this->addFlash('error', 'Rôle invalide.');
                return $this->redirectToRoute('app_users');
            }

            // Check if email already exists
            if ($userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_users');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setRoles([$role]);
            $user->setPassword($hasher->hashPassword($user, $password));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_users');
        }

        return $this->render('user/index.html.twig', ['users' => $users]);
    }
    
    #[Route('/admin/users/{id}/edit', name: 'app_users_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $userRepository->find($id);
        if (!$user) throw $this->createNotFoundException('Utilisateur introuvable.');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_user_' . $id, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $role = $request->request->get('role');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Email invalide.');
                return $this->redirectToRoute('app_users_edit', ['id' => $id]);
            }

            if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN'])) {
                $this->addFlash('error', 'Rôle invalide.');
                return $this->redirectToRoute('app_users_edit', ['id' => $id]);
            }

            $existing = $userRepository->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $id) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_users_edit', ['id' => $id]);
            }

            $user->setEmail($email);
            $user->setRoles([$role]);

            if ($password) {
                if (strlen($password) < 8) {
                    $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');
                    return $this->redirectToRoute('app_users_edit', ['id' => $id]);
                }
                $user->setPassword($hasher->hashPassword($user, $password));
            }

            $em->flush();
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('app_users');
        }

        return $this->render('user/edit.html.twig', ['user' => $user]);
    }

    #[Route('/admin/users/{id}/delete', name: 'app_users_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);
        if (!$user) throw $this->createNotFoundException('Utilisateur introuvable.');

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_users');
        }

        if ($this->isCsrfTokenValid('delete_user_' . $id, $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('app_users');
    }
}