<?php

declare(strict_types=1);

namespace App\Controllers\Moderation;

use App\Models\ReportStatus;
use App\Controllers\Controller;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Services\ModerationService;
use App\Services\ReportService;
use App\Services\TopicService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class ReportController extends Controller
{
    private ReportRepository $reports;

    private ReportService $service;

    public function __construct()
    {
        parent::__construct();

        $this->reports = new ReportRepository();
        $this->service = new ReportService();
    }

    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'pending');

        if (!in_array($status, ReportStatus::filters(), true)) {
            $status = 'pending';
        }

        $paginator = $this->reports->paginate(
            $status,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('moderation.reports'),
        );

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Reports');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/reports', [
            'paginator' => $paginator,
            'status' => $status,
            'counts' => [
                'pending' => $this->reports->countByStatus(ReportStatus::Pending->value),
                'resolved' => $this->reports->countByStatus(ReportStatus::Resolved->value),
                'dismissed' => $this->reports->countByStatus(ReportStatus::Dismissed->value),
            ],
            'reasons' => ReportService::REASONS,
        ]);
    }

    public function show(Request $request): Response
    {
        $report = $this->findOrFail((int) $request->route('id'));

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Report #' . (int) $report['id']);
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/report-show', [
            'report' => $report,
            'content' => $this->service->resolveContent($report),
            'reasons' => ReportService::REASONS,
            'history' => $report['reported_user_id'] === null
                ? []
                : $this->reports->forUser((int) $report['reported_user_id'], 10),
        ]);
    }

    /**
     * Applies the moderator's decision: an optional content action plus the
     * resolution itself, both written to the moderation log.
     */
    public function handle(Request $request): Response
    {
        $report = $this->findOrFail((int) $request->route('id'));
        $moderatorId = $this->userId();

        $decision = (string) $request->input('decision', 'resolve');
        $contentAction = (string) $request->input('content_action', 'none');
        $notes = $request->text('notes');

        if (!in_array($decision, ['resolve', 'dismiss'], true)) {
            $decision = 'resolve';
        }

        $applied = $this->applyContentAction($report, $contentAction, $request);

        $this->reports->resolve(
            (int) $report['id'],
            $moderatorId,
            ($decision === 'resolve' ? ReportStatus::Resolved : ReportStatus::Dismissed)->value,
            $applied,
            $notes === '' ? null : $notes,
        );

        (new ModerationService())->record(
            $moderatorId,
            'report.' . $decision,
            'report',
            (int) $report['id'],
            sprintf(
                '%s report #%d (%s)',
                $decision === 'resolve' ? 'Resolved' : 'Dismissed',
                (int) $report['id'],
                $applied,
            ),
            $notes === '' ? null : $notes,
            $report['reported_user_id'] === null ? null : (int) $report['reported_user_id'],
            ['content_type' => $report['content_type'], 'content_id' => $report['content_id']],
            $request->ip(),
        );

        Flash::success(sprintf('Report #%d %s.', (int) $report['id'], $decision === 'resolve' ? 'resolved' : 'dismissed'));

        return $this->redirect(Url::route('moderation.reports'));
    }

    /**
     * @param array<string,mixed> $report
     * @return string The action actually taken, recorded on the report.
     */
    private function applyContentAction(array $report, string $action, Request $request): string
    {
        if ($action === 'none' || (string) $report['content_type'] !== 'post') {
            return $action === 'none' ? 'no action' : 'no action (unsupported content type)';
        }

        $posts = new PostRepository();
        $post = $posts->find((int) $report['content_id']);

        if ($post === null) {
            return 'content already gone';
        }

        if (!$this->access->canModerateForum((int) $post['forum_id'])) {
            throw HttpException::forbidden('You do not moderate the forum this content belongs to.');
        }

        $service = new TopicService();

        return match ($action) {
            'hide' => $this->hidePost($service, $post),
            'delete' => $this->deletePost($service, $post),
            default => 'no action',
        };
    }

    /** @param array<string,mixed> $post */
    private function hidePost(TopicService $service, array $post): string
    {
        $service->setPostHidden($post, true);

        return 'post hidden';
    }

    /** @param array<string,mixed> $post */
    private function deletePost(TopicService $service, array $post): string
    {
        $service->deletePost($post, $this->userId());

        return 'post deleted';
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $report = $this->reports->find($id);

        if ($report === null) {
            throw HttpException::notFound('There is no report with that number.');
        }

        return $report;
    }
}
