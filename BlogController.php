<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\StrapiService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class BlogController extends AbstractController
{
    #[Route('/blog', name: 'app_blog_index')]
    public function index(StrapiService $strapiService): Response
    {
        $response = $strapiService->getArticles();

        $posts = $response['data'] ?? [];

        $articles = array_map(function($post) {
            // Check for nested image data.
            $coverUrl = $post['cover']['formats']['small']['url'] ?? $post['cover']['url'] ?? null;
            
            // Re-format the data for consistency
            return [
                'id'          => $post['id'],
                'documentId'  => $post['documentId'],
                'title'       => $post['title'],
                'description' => $post['description'],
                'slug'        => $post['slug'],
                'publishedAt' => $post['publishedAt'],
                'coverUrl'    => $coverUrl,
                'cover'       => $post['cover'] ?? null,
                'author'      => $post['author'] ?? null,
                'blocks'      => $post['blocks'] ?? null,
            ];
        }, $posts);

        return $this->render('blog/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/blog/strapi', name: 'app_blog_in_develeopement')]
    public function strapi(): Response
    {
        $feature = 'Blog Content Management';
        $specs = 'A moderator-controlled, Strapi-based CMS solution. It will enable the creation and moderation of articles with predefined formats. All content will be loaded dynamically from the Strapi database and rendered via this Twig template.';

        return $this->render('featureInDevelopement.html.twig', [
            'feature' => $feature,
            'specs' => $specs
        ]);
    }

    #[Route('/cms/strapi', name: 'app_CMS_in_develeopement')]
    public function cms(): Response
    {
        $feature = 'Blog Content Management';
        $specs = 'A moderator-controlled, Strapi-based CMS solution. It will enable the creation and moderation of articles with predefined formats. All content will be loaded dynamically from the Strapi database and rendered via this Twig template.';
        
        return $this->render('admin/featureInDevelopement.html.twig', [
            'feature' => $feature,
            'specs' => $specs,
            'active_section' => 'Blog',
        ]);
    }


    #[Route('/blog/{documentId}', name: 'app_blog_show')]
    public function showById(StrapiService $strapiService, string $documentId): Response
    {
        $post = $strapiService->getArticleById($documentId);

        if (!$post) {
            throw new NotFoundHttpException('The requested article could not be found.');
        }

        $article = $post['attributes'] ?? $post;
        return $this->render('blog/show.html.twig', [
            'article' => $article,
        ]);
    }
}