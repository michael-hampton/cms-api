<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary-blue: #0ea5e9;
        --dark-blue: #0284c7;
        --green: #10b981;
        --dark-green: #059669;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;
        --bg-light: #f9fafb;
        --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .top-filters {
        background: var(--bg-light);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-group label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        background: white;
        font-size: 0.875rem;
        cursor: pointer;
    }
</style>

<div class="top-filters">
    <button class="size-btn active">All Sizes</button>
    <button class="size-btn">Up to 43-in...</button>
    <button class="size-btn">48 to 55-inch</button>
    <button class="size-btn">65-inch</button>
    <button class="size-btn">75-inch</button>
    <button class="size-btn">85 to 115-inch</button>
</div>