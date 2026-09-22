<?php
/** @var App\Support\View $this */
?>
<footer class="board-footer">
    <div class="shell footer-inner">
        <div class="footer-links">
            <a href="<?= $this->e($this->url('/')) ?>">Board index</a>
            <a href="<?= $this->e($this->route('rules')) ?>">Rules</a>
            <a href="<?= $this->e($this->route('help')) ?>">Formatting</a>
            <a href="<?= $this->e($this->route('members')) ?>">Members</a>
            <a href="<?= $this->e($this->route('online')) ?>">Who is online</a>
            <a href="<?= $this->e($this->route('search')) ?>">Search</a>
        </div>
        <p class="footer-meta">
            <span class="mono"><?= $this->e($this->shared('site_name', 'Coldwire')) ?></span>
            · all times <?= $this->e((string) $this->shared('viewer_timezone', 'UTC')) ?>
        </p>
    </div>
</footer>
