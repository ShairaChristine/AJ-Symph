<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Note;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;


class NotesController extends AbstractController
{
    private const STATUS_LABELS = [
        'new' => 'New',
        'todo' => 'Todo',
        'done' => 'Done',
    ];

    /**
     * @Route("/notes", name="app_notes")
     */
    public function notes(Request $request, EntityManagerInterface $entityManager): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', 'all'); // 'all' means no filter
        $category = (string) $request->query->get('category', 'all'); // 'all' means no filter

        $qb = $entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->orderBy('n.createdAt', 'DESC');

        if ($status !== 'all') {
            $qb
                ->andWhere('n.status = :status')
                ->setParameter('status', $status);
        }

        if ($category !== 'all') {
            $qb
                ->andWhere('n.category = :category')
                ->setParameter('category', $category);
        }

        if ($q !== '') {
            $needle = '%' . mb_strtolower($q) . '%';
            $qb
                ->andWhere('LOWER(n.title) LIKE :needle OR LOWER(n.content) LIKE :needle')
                ->setParameter('needle', $needle);
        }

        $notes = $qb->getQuery()->getResult();
        $categories = $this->getCategories($entityManager);
        
        if ($q === '/notes') {
            $q = '';
        }

        return $this->render('notes.html.twig', [
            'notes' => $notes,
            'statuses' => self::STATUS_LABELS,
            'categories' => $categories,
            'query' => [
                'q' => $q,
                'status' => $status,
                'category' => $category,
            ],
        ]);
    }

    /**
     * @Route("/notes/new", name="app_notes_new", methods={"POST"})
     */
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        $status = (string) $request->request->get('status', 'new');
        if (!isset(self::STATUS_LABELS[$status])) {
            $status = 'new';
        }

        $categorySelect = (string) $request->request->get('category', '__custom__');
        $customCategory = trim((string) $request->request->get('custom_category', ''));

        $category = $categorySelect === '__custom__' ? $customCategory : $categorySelect;
        $category = trim((string) $category);

        if ($title === '' || $content === '' || $category === '') {
            return $this->redirectToRoute('app_notes');
        }

        $this->addNote($entityManager, $title, $content, $category, $status);

        return $this->redirectToRoute('app_notes');
    }

    private function addNote(
        EntityManagerInterface $entityManager,
        string $title,
        string $content,
        string $category,
        string $status
    ): void {
        $note = new Note();
        $note
            ->setTitle($title)
            ->setContent($content)
            ->setCategory($category)
            ->setStatus($status);

        $entityManager->persist($note);
        $entityManager->flush();
    }

    /**
     * @return string[]
     */
    private function getCategories(EntityManagerInterface $entityManager): array
    {
        $qb = $entityManager->createQueryBuilder()
            ->select('DISTINCT n.category AS category')
            ->from(Note::class, 'n')
            ->orderBy('n.category', 'ASC');

        $rows = $qb->getQuery()->getArrayResult();

        $categories = array_map(static function (array $row): string {
            return (string) $row['category'];
        }, $rows);

        // Keep output deterministic even if DB returns odd ordering.
        sort($categories);

        return $categories;
    }

    #[Route('/notes/delete/{id}', name: 'app_notes_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $note = $em->getRepository(Note::class)->find($id);

        if (!$note) {
            throw $this->createNotFoundException('Note not found');
        }

        if ($this->isCsrfTokenValid('delete_note_' . $note->getId(), $request->request->get('_token'))) {
            $em->remove($note);
            $em->flush();
        }

        return $this->redirectToRoute('app_notes');
    }
}

