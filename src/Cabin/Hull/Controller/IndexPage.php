<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Controller;

use Airship\Cabin\Hull\Model\Blog;
use ParagonIE\ConstantTime\Binary;

require_once __DIR__.'/init_gear.php';

/**
 * Class IndexPage
 * @package Airship\Cabin\Hull\Controller
 */
class IndexPage extends ControllerGear
{
    /**
     * @var Blog
     */
    protected $blog;

    /**
     * The homepage for an Airship.
     *
     * @route /
     */
    public function index()
    {
        $this->blog = $this->model('Blog');

        if (!\file_exists(ROOT . '/public/robots.txt')) {
            // Default robots.txt
            \file_put_contents(
                ROOT . '/public/robots.txt',
                "User-agent: *\nAllow: /"
            );
        }

        $blogRoll = $this->blog->recentFullPosts(
            (int) ($this->config('homepage.blog-posts') ?? 5)
        );
        $mathJAX = false;
        foreach ($blogRoll as $i => $blog) {
            $blogRoll[$i] = $this->blog->getSnippet($blog);
            if (Binary::safeStrlen($blogRoll[$i]['snippet']) !== Binary::safeStrlen($blog['body'])) {
                $blogRoll[$i]['snippet'] = \rtrim($blogRoll[$i]['snippet'], "\n");
            }
            $mathJAX |= \strpos($blog['body'], '$$') !== false;
        }

        $args = [
            'blogposts' => $blogRoll
        ];
        $this->config('blog.cachelists')
            ? $this->stasis('index', $args)
            : $this->view('index', $args);
    }

    /**
     *
     * @route motif_extra.css
     */
    public function motifExtra()
    {
        $this->view('motif_extra', [], 'text/css; charset=UTF-8');
    }
}
