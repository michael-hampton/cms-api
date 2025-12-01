<?php if (isset($pagination) && $pagination['last_page'] > 1): ?>
    <div class="pagination">
        <?php if ($pagination['current_page'] > 1): ?>
            <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="pagination-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                Previous
            </a>
        <?php endif; ?>

        <?php
        $start = max(1, $pagination['current_page'] - 2);
        $end = min($pagination['last_page'], $pagination['current_page'] + 2);

        if ($start > 1): ?>
            <a href="?page=1" class="pagination-link">1</a>
            <?php if ($start > 2): ?>
                <span class="pagination-ellipsis">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $pagination['current_page']): ?>
                <span class="pagination-link active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>" class="pagination-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $pagination['last_page']): ?>
            <?php if ($end < $pagination['last_page'] - 1): ?>
                <span class="pagination-ellipsis">...</span>
            <?php endif; ?>
            <a href="?page=<?= $pagination['last_page'] ?>" class="pagination-link"><?= $pagination['last_page'] ?></a>
        <?php endif; ?>

        <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
            <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="pagination-link">
                Next
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18l6-6-6-6"/>
                </svg>
            </a>
        <?php endif; ?>
    </div>

    <style>
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .pagination-link:hover:not(.active) {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.15);
        }

        .pagination-link.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
            cursor: default;
        }

        .pagination-ellipsis {
            padding: 10px 8px;
            color: #9ca3af;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .pagination {
                gap: 6px;
            }

            .pagination-link {
                padding: 8px 12px;
                font-size: 0.875rem;
            }
        }
    </style>
<?php endif; ?>