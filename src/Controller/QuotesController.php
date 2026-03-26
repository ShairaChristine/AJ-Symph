<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuotesController extends AbstractController
{
    /**
     * @Route("/quotes", name="app_quotes", methods={"GET"})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $quotes = $entityManager->getRepository(Quote::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('quotes/index.html.twig', [
            'quotes' => $quotes,
        ]);
    }

    /**
     * @Route("/quotes/new", name="app_quotes_new", methods={"POST"})
     */
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            return $this->redirectToRoute('app_quotes');
        }

        $this->addQuote($entityManager, $content);

        return $this->redirectToRoute('app_quotes');
    }

    private function addQuote(EntityManagerInterface $entityManager, string $content): void
    {
        $quote = new Quote();
        $quote->setContent($content);

        $entityManager->persist($quote);
        $entityManager->flush();
    }
}

