<?php

declare(strict_types=1);

use App\Support\Paginator;
use Tests\Assert;

return [
    'page count rounds up' => static function (): void {
        Assert::same(4, (new Paginator([], 58, 15, 1, '/t'))->lastPage());
        Assert::same(1, (new Paginator([], 15, 15, 1, '/t'))->lastPage());
        Assert::same(1, (new Paginator([], 0, 15, 1, '/t'))->lastPage(), 'an empty set is still one page');
    },

    'item numbering reflects the offset' => static function (): void {
        $page = new Paginator([], 58, 15, 3, '/t');

        Assert::same(31, $page->firstItemNumber());
        Assert::same(45, $page->lastItemNumber());
    },

    'the last page is not over-counted' => static function (): void {
        $page = new Paginator([], 58, 15, 4, '/t');

        Assert::same(58, $page->lastItemNumber());
    },

    'empty results report zero' => static function (): void {
        $page = new Paginator([], 0, 15, 1, '/t');

        Assert::same(0, $page->firstItemNumber());
        Assert::false($page->hasPages());
    },

    'urls omit page=1 and keep other query values' => static function (): void {
        $page = new Paginator([], 100, 10, 2, '/search', ['q' => 'dns']);

        Assert::same('/search?q=dns', $page->url(1));
        Assert::contains('page=3', $page->url(3));
        Assert::contains('q=dns', $page->url(3));
    },

    'urls are clamped to the available range' => static function (): void {
        $page = new Paginator([], 30, 10, 1, '/t');

        Assert::same($page->url(3), $page->url(99), 'beyond the last page clamps to it');
    },

    'previous and next disappear at the edges' => static function (): void {
        $first = new Paginator([], 30, 10, 1, '/t');
        Assert::null($first->previousUrl());
        Assert::notNull($first->nextUrl());

        $last = new Paginator([], 30, 10, 3, '/t');
        Assert::notNull($last->previousUrl());
        Assert::null($last->nextUrl());
    },

    'the window elides the middle of a long range' => static function (): void {
        $window = (new Paginator([], 1000, 10, 50, '/t'))->window();

        Assert::same(1, $window[0]);
        Assert::same(100, $window[count($window) - 1]);
        Assert::true(in_array(null, $window, true), 'an ellipsis marker is present');
        Assert::true(in_array(50, $window, true), 'the current page is present');
    },

    'short ranges are listed in full' => static function (): void {
        Assert::same([1, 2, 3], (new Paginator([], 30, 10, 2, '/t'))->window());
    },

    'offset helper' => static function (): void {
        Assert::same(0, Paginator::offset(1, 15));
        Assert::same(30, Paginator::offset(3, 15));
        Assert::same(0, Paginator::offset(-5, 15), 'negative pages are clamped');
    },
];
