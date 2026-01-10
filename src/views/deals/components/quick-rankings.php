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

    .quick-rankings {
        margin-bottom: 2rem;
    }

    .rankings-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .rankings-header h3 {
        font-size: 1rem;
        font-weight: 700;
    }

    .ranking-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .ranking-tab {
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 1.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ranking-tab:hover,
    .ranking-tab.active {
        background: var(--text-primary);
        color: white;
        border-color: var(--text-primary);
    }

    .price-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .price-tab {
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 1.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .price-tab:hover,
    .price-tab.active {
        background: var(--green);
        color: white;
        border-color: var(--green);
    }
</style>

<div class="quick-rankings">
    <div class="rankings-header">
        <span class="logo" style="font-size: 1.25rem;">tom's guide</span>
        <h3>QUICK RANKINGS:</h3>
    </div>
    <div class="ranking-tabs">
        <button class="ranking-tab active">All Guides</button>
        <button class="ranking-tab">Best Overall</button>
        <button class="ranking-tab">Best OLED</button>
        <button class="ranking-tab">Best Budget</button>
        <button class="ranking-tab">Best 43-inch</button>
        <button class="ranking-tab">Best 55-inch</button>
        <button class="ranking-tab">Best 65-inch</button>
        <button class="ranking-tab">Best 77-inch</button>
    </div>
    <div class="price-tabs">
        <button class="price-tab active">All Prices</button>
        <button class="price-tab">Up to $500</button>
        <button class="price-tab">Up to $1,000</button>
        <button class="price-tab">Up to $1,500</button>
        <button class="price-tab">Up to $2,000</button>
        <button class="price-tab">Up to $3,000</button>
        <button class="price-tab">$3,000+</button>
    </div>
</div>