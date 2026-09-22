<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ForumRepository;
use App\Repositories\SearchRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $criteria = [
            'keywords' => (string) $request->input('q', ''),
            'author' => (string) $request->input('author', ''),
            'forum' => $request->int('forum', 0),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'mode' => $request->input('mode', 'posts') === 'topics' ? 'topics' : 'posts',
        ];

        $minLength = $this->settings->int('search_min_length', 3);
        $hasCriteria = $criteria['keywords'] !== '' || $criteria['author'] !== '' || $criteria['forum'] > 0;
        $paginator = null;
        $notice = null;

        if ($criteria['keywords'] !== '' && mb_strlen($criteria['keywords'], 'UTF-8') < $minLength) {
            $notice = sprintf('Search terms must be at least %d characters long.', $minLength);
        } elseif ($hasCriteria) {
            $paginator = (new SearchRepository())->search(
                $criteria,
                $this->access->readableForumIds(),
                $this->page($request),
                $this->settings->itemsPerPage(),
                Url::route('search'),
            );
        }

        $this->view->title('Search');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Search'],
        ]);

        return $this->render('search/index', [
            'criteria' => $criteria,
            'paginator' => $paginator,
            'notice' => $notice,
            'forums' => $this->searchableForums(),
            'terms' => array_slice(array_filter(preg_split('/\s+/', $criteria['keywords']) ?: []), 0, 6),
        ]);
    }

    /** Recent activity feed — "view new posts" in traditional board terms. */
    public function recent(Request $request): Response
    {
        $paginator = (new SearchRepository())->search(
            ['mode' => 'topics'],
            $this->access->readableForumIds(),
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('search.recent'),
        );

        $this->view->title('Recent topics');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Recent topics'],
        ]);

        return $this->render('search/recent', ['paginator' => $paginator]);
    }

    /** @return array<int,array<string,mixed>> */
    private function searchableForums(): array
    {
        $readable = array_flip($this->access->readableForumIds());
        $list = [];

        foreach ((new ForumRepository())->allForums(false) as $forum) {
            if (isset($readable[(int) $forum['id']])) {
                $list[] = $forum;
            }
        }

        return $list;
    }
}
