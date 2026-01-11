<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Repositories\Cms\CategoryRepository;

class PageCategoryAssignmentSeeder extends Seeder
{
    private $categoryRepository;

    public function __construct()
    {
        $this->categoryRepository = new CategoryRepository();
        parent::__construct();
    }

    public function run(): void
    {
//        $this->assignTechWeeklyCategories();
//        $this->assignHavenHearthCategories();
//        $this->assignSoundwaveCategories();
//        $this->assignWineChronicleCategories();
//        $this->assignMusicWeekCategories();
//        $this->assignGamesRadarCategories();
//        $this->assignHorseAndHoundCategories();
        $this->assignGoCompareCategories();
        $this->assignVogueNoirCategories();
    }

    private function assignGoCompareCategories(): void
    {
        $siteId = 28;

        // Insurance Categories
        $carInsuranceCategory = $this->categoryRepository->findOrCreateByName('Car Insurance', $siteId);
        $this->assignPagesToCategory([269], $carInsuranceCategory->id);

        $homeInsuranceCategory = $this->categoryRepository->findOrCreateByName('Home Insurance', $siteId);
        $this->assignPagesToCategory([270], $homeInsuranceCategory->id);

        $travelInsuranceCategory = $this->categoryRepository->findOrCreateByName('Travel Insurance', $siteId);
        $this->assignPagesToCategory([271], $travelInsuranceCategory->id);

        $petInsuranceCategory = $this->categoryRepository->findOrCreateByName('Pet Insurance', $siteId);
        $this->assignPagesToCategory([275], $petInsuranceCategory->id);

        $lifeInsuranceCategory = $this->categoryRepository->findOrCreateByName('Life Insurance', $siteId);
        $this->assignPagesToCategory([276], $lifeInsuranceCategory->id);

        $healthInsuranceCategory = $this->categoryRepository->findOrCreateByName('Health Insurance', $siteId);
        $this->assignPagesToCategory([276], $healthInsuranceCategory->id);

        // Money Categories
        $creditCardsCategory = $this->categoryRepository->findOrCreateByName('Credit Cards', $siteId);
        $this->assignPagesToCategory([273], $creditCardsCategory->id);

        $loansCategory = $this->categoryRepository->findOrCreateByName('Loans', $siteId);
        $this->assignPagesToCategory([273], $loansCategory->id);

        $mortgagesCategory = $this->categoryRepository->findOrCreateByName('Mortgages', $siteId);
        $this->assignPagesToCategory([270], $mortgagesCategory->id);

        $bankAccountsCategory = $this->categoryRepository->findOrCreateByName('Bank Accounts', $siteId);
        $this->assignPagesToCategory([273], $bankAccountsCategory->id);

        $savingsCategory = $this->categoryRepository->findOrCreateByName('Savings', $siteId);
        $this->assignPagesToCategory([276], $savingsCategory->id);

        // Utilities Categories
        $energyCategory = $this->categoryRepository->findOrCreateByName('Energy', $siteId);
        $this->assignPagesToCategory([272], $energyCategory->id);

        $broadbandCategory = $this->categoryRepository->findOrCreateByName('Broadband', $siteId);
        $this->assignPagesToCategory([274], $broadbandCategory->id);

        $mobilePhonesCategory = $this->categoryRepository->findOrCreateByName('Mobile Phones', $siteId);
        $this->assignPagesToCategory([274], $mobilePhonesCategory->id);

        $tvStreamingCategory = $this->categoryRepository->findOrCreateByName('TV & Streaming', $siteId);
        $this->assignPagesToCategory([274], $tvStreamingCategory->id);

        // Guides & Advice Categories
        $howToGuidesCategory = $this->categoryRepository->findOrCreateByName('How-To Guides', $siteId);
        $this->assignPagesToCategory([269, 270, 271, 272, 273, 274, 275, 276], $howToGuidesCategory->id);

        $moneySavingTipsCategory = $this->categoryRepository->findOrCreateByName('Money Saving Tips', $siteId);
        $this->assignPagesToCategory([272, 273], $moneySavingTipsCategory->id);

        $comparisonGuidesCategory = $this->categoryRepository->findOrCreateByName('Comparison Guides', $siteId);
        $this->assignPagesToCategory([270, 274], $comparisonGuidesCategory->id);
    }

    private function assignPagesToCategory(array $pageIds, int $categoryId): void
    {
        foreach ($pageIds as $pageId) {
            $page = Page::find($pageId);
            if ($page) {
                // Check if relationship already exists
                $exists = PageCategory::where('page_id', $pageId)
                    ->where('category_id', $categoryId)
                    ->exists();

                if (!$exists) {
                    $page->categories(true)->attach($categoryId);
                }
            }
        }
    }

    private function assignVogueNoirCategories(): void
    {
        $siteId = 6;

        // Runway Shows
        $runwayCategory = $this->categoryRepository->findOrCreateByName('Runway Shows', $siteId);
        $this->assignPagesToCategory([25, 193], $runwayCategory->id);

        // Street Style
        $streetStyleCategory = $this->categoryRepository->findOrCreateByName('Street Style', $siteId);
        $this->assignPagesToCategory([27, 313], $streetStyleCategory->id);

        // Trends
        $trendsCategory = $this->categoryRepository->findOrCreateByName('Trends', $siteId);
        $this->assignPagesToCategory([194, 195], $trendsCategory->id);

        // Sustainable Fashion
        $sustainableCategory = $this->categoryRepository->findOrCreateByName('Sustainable Fashion', $siteId);
        $this->assignPagesToCategory([26, 191], $sustainableCategory->id);

        // Buying Guides (Shopping)
        $buyingGuidesCategory = $this->categoryRepository->findOrCreateByName('Buying Guides', $siteId);
        $this->assignPagesToCategory([192, 195], $buyingGuidesCategory->id);

        // Luxury (Shopping)
        $luxuryCategory = $this->categoryRepository->findOrCreateByName('Luxury', $siteId);
        $this->assignPagesToCategory([192], $luxuryCategory->id);

        // Accessories (Shopping)
        $accessoriesCategory = $this->categoryRepository->findOrCreateByName('Accessories', $siteId);
        $this->assignPagesToCategory([192], $accessoriesCategory->id);

        // Makeup (Beauty)
        $makeupCategory = $this->categoryRepository->findOrCreateByName('Makeup', $siteId);
        $this->assignPagesToCategory([194], $makeupCategory->id);

        // Skincare (Beauty)
        $skincareCategory = $this->categoryRepository->findOrCreateByName('Skincare', $siteId);
        $this->assignPagesToCategory([195], $skincareCategory->id);

        // Designer Profiles
        $designerProfilesCategory = $this->categoryRepository->findOrCreateByName('Designer Profiles', $siteId);
        $this->assignPagesToCategory([26, 191], $designerProfilesCategory->id);

        // Emerging Designers
        $emergingDesignersCategory = $this->categoryRepository->findOrCreateByName('Emerging Designers', $siteId);
        $this->assignPagesToCategory([26], $emergingDesignersCategory->id);

        // Interviews (Features)
        $interviewsCategory = $this->categoryRepository->findOrCreateByName('Interviews', $siteId);
        $this->assignPagesToCategory([26, 191], $interviewsCategory->id);

        // Style Guides (Features)
        $styleGuidesCategory = $this->categoryRepository->findOrCreateByName('Style Guides', $siteId);
        $this->assignPagesToCategory([195], $styleGuidesCategory->id);
    }

    private function assignTechWeeklyCategories(): void
    {
        $siteId = 2;

        // --- Existing Assignments ---
        // Assign to TVs category
        $tvsCategory = $this->categoryRepository->findOrCreateByName('TVs', $siteId);
        $this->assignPagesToCategory([224, 229, 417], $tvsCategory->id);

        // Assign to Smartphones category
        $smartphonesCategory = $this->categoryRepository->findOrCreateByName('Smartphones', $siteId);
        $this->assignPagesToCategory([225, 231, 419, 421], $smartphonesCategory->id);

        // Assign to Laptops category
        $laptopsCategory = $this->categoryRepository->findOrCreateByName('Laptops', $siteId);
        $this->assignPagesToCategory([226, 230, 420, 422], $laptopsCategory->id);

        // Assign to Audio category
        $audioCategory = $this->categoryRepository->findOrCreateByName('Audio', $siteId);
        $this->assignPagesToCategory([227], $audioCategory->id);

        // Assign to Gaming category
        $gamingCategory = $this->categoryRepository->findOrCreateByName('Gaming', $siteId);
        $this->assignPagesToCategory([228], $gamingCategory->id);

        // Assign tech articles to Technology category
        $techCategory = Category::where('name', 'Technology')->where('site_id', $siteId)->first();
        if ($techCategory) {
            $this->assignPagesToCategory([18, 19, 172], $techCategory->id);
        }

        // Assign to Security category
        $securityCategory = Category::where('name', 'Security')->where('site_id', $siteId)->first();
        if ($securityCategory) {
            $this->assignPagesToCategory([18], $securityCategory->id);
        }

        // Assign to Development category
        $devCategory = Category::where('name', 'Development')->where('site_id', $siteId)->first();
        if ($devCategory) {
            $this->assignPagesToCategory([19, 172], $devCategory->id);
        }

        // --- New Assignments for Menu-defined Categories ---

        // Missing from current assignments based on MenuReorganizationSeeder:
        // LG (Page 423)
        $lgCategory = $this->categoryRepository->findOrCreateByName('LG', $siteId);
        $this->assignPagesToCategory([423], $lgCategory->id);

        // Sony (Already has pages in TVs assignment, adding more for completeness)
        $sonyCategory = $this->categoryRepository->findOrCreateByName('Sony', $siteId);
        $this->assignPagesToCategory([417, 229], $sonyCategory->id); // Using existing pages

        // Apple (Already has pages in Smartphones assignment, adding more for completeness)
        $appleCategory = $this->categoryRepository->findOrCreateByName('Apple', $siteId);
        $this->assignPagesToCategory([231, 419], $appleCategory->id); // Using existing pages

        // Samsung (Mentioned in menu, assign smartphones page)
        $samsungCategory = $this->categoryRepository->findOrCreateByName('Samsung', $siteId);
        $this->assignPagesToCategory([421], $samsungCategory->id);

        // Google (Mentioned in menu, use an arbitrary page since none is obviously suitable in pages.sql)
        $googleCategory = $this->categoryRepository->findOrCreateByName('Google', $siteId);
        $this->assignPagesToCategory([225], $googleCategory->id);

        // Microsoft (Mentioned in menu, assign laptops page)
        $microsoftCategory = $this->categoryRepository->findOrCreateByName('Microsoft', $siteId);
        $this->assignPagesToCategory([230, 422], $microsoftCategory->id);

        // Nokia (Mentioned in menu, use an arbitrary page)
        $nokiaCategory = $this->categoryRepository->findOrCreateByName('Nokia', $siteId);
        $this->assignPagesToCategory([419], $nokiaCategory->id);

        // Buying Guides (Using a TV guide page)
        $buyingGuidesCategory = $this->categoryRepository->findOrCreateByName('Buying Guides', $siteId);
        $this->assignPagesToCategory([224], $buyingGuidesCategory->id);

        // Note: The menu reorganize uses custom URLs for most categories, so we must rely on explicit names here.
    }

    private function assignHavenHearthCategories(): void
    {
        $siteId = 8;

        // --- Existing Assignments ---
        // Interior Design categories
        $interiorCategory = Category::where('name', 'Interior Design')->where('site_id', $siteId)->first();
        if ($interiorCategory) {
            $this->assignPagesToCategory([40, 207, 313], $interiorCategory->id);
        }

        // Living Spaces - Living Room
        $livingRoomCategory = Category::where('name', 'Living Room')->where('site_id', $siteId)->first();
        if ($livingRoomCategory) {
            $this->assignPagesToCategory([207, 313], $livingRoomCategory->id);
        }

        // Design Styles - Scandinavian
        $scandinavianCategory = Category::where('name', 'Scandinavian')->where('site_id', $siteId)->first();
        if ($scandinavianCategory) {
            $this->assignPagesToCategory([40, 207], $scandinavianCategory->id);
        }

        // Lighting category
        $lightingCategory = Category::where('name', 'Lighting')->where('site_id', $siteId)->first();
        if ($lightingCategory) {
            $this->assignPagesToCategory([41], $lightingCategory->id);
        }

        // Garden & Outdoor categories
        $gardenCategory = Category::where('name', 'Garden & Outdoor')->where('site_id', $siteId)->first();
        if ($gardenCategory) {
            $this->assignPagesToCategory([42, 204, 206, 209], $gardenCategory->id);
        }

        // Gardening
        $gardeningCategory = Category::where('name', 'Gardening')->where('site_id', $siteId)->first();
        if ($gardeningCategory) {
            $this->assignPagesToCategory([42, 204, 206, 209], $gardeningCategory->id);
        }

        // DIY & Projects
        $diyCategory = Category::where('name', 'DIY & Projects')->where('site_id', $siteId)->first();
        if ($diyCategory) {
            $this->assignPagesToCategory([180, 208, 314], $diyCategory->id);
        }

        // Product Reviews
        $reviewsCategory = Category::where('name', 'Product Reviews')->where('site_id', $siteId)->first();
        if ($reviewsCategory) {
            $this->assignPagesToCategory([41], $reviewsCategory->id);
        }

        // Buying Guides
        $guidesCategory = Category::where('name', 'Buying Guides')->where('site_id', $siteId)->first();
        if ($guidesCategory) {
            $this->assignPagesToCategory([205], $guidesCategory->id);
        }

        // Storage Solutions
        $storageCategory = Category::where('name', 'Storage Solutions')->where('site_id', $siteId)->first();
        if ($storageCategory) {
            $this->assignPagesToCategory([180, 208, 298, 314], $storageCategory->id);
        }

        // --- New Assignments for Menu-defined Categories (Haven & Hearth) ---
        // Need to create or find and assign a page to all categories mentioned in MenuReorganizationSeeder::reorganizeHavenHearthMenu()
        // Page 182 (Choosing the Perfect Paint Color: A Room-by-Room Guide) will be used for color/paint categories.
        // Page 210 (Farmhouse Kitchen Renovation: £5,000 Budget Makeover) will be used for Kitchen/Renovation categories.
        // Page 181 (Complete Guide to Growing Herbs Indoors Year-Round) will be used for Plant/Herb care.

        // Interior Design Topics (from menu)
        $this->assignPagesToCategory([207], $this->categoryRepository->findOrCreateByName('Living Room Ideas', $siteId)->id);
        $this->assignPagesToCategory([182], $this->categoryRepository->findOrCreateByName('Bedroom Design', $siteId)->id);
        $this->assignPagesToCategory([210], $this->categoryRepository->findOrCreateByName('Kitchen Inspiration', $siteId)->id);
        $this->assignPagesToCategory([182], $this->categoryRepository->findOrCreateByName('Bathroom Makeovers', $siteId)->id);
        $this->assignPagesToCategory([182], $this->categoryRepository->findOrCreateByName('Color & Paint', $siteId)->id);
        $this->assignPagesToCategory([41], $this->categoryRepository->findOrCreateByName('Lighting Design', $siteId)->id);

        // Garden & Outdoor Topics (from menu)
        $this->assignPagesToCategory([42], $this->categoryRepository->findOrCreateByName('Gardening Tips', $siteId)->id);
        $this->assignPagesToCategory([181], $this->categoryRepository->findOrCreateByName('Plant Care', $siteId)->id);
        $this->assignPagesToCategory([42], $this->categoryRepository->findOrCreateByName('Outdoor Living', $siteId)->id);
        $this->assignPagesToCategory([42], $this->categoryRepository->findOrCreateByName('Landscaping Ideas', $siteId)->id);
        $this->assignPagesToCategory([42], $this->categoryRepository->findOrCreateByName('Seasonal Guides', $siteId)->id);

        // DIY & Projects Topics (from menu)
        $this->assignPagesToCategory([210], $this->categoryRepository->findOrCreateByName('Home Improvements', $siteId)->id);
        // Storage Solutions is already assigned
        $this->assignPagesToCategory([208], $this->categoryRepository->findOrCreateByName('Furniture Projects', $siteId)->id);
        $this->assignPagesToCategory([180], $this->categoryRepository->findOrCreateByName('Crafts & Decor', $siteId)->id);

        // Product Reviews (already assigned)
    }

    private function assignSoundwaveCategories(): void
    {
        $siteId = 7;

        // --- Existing Assignments ---
        // Features - Interviews
        $interviewsCategory = Category::where('name', 'Interviews')->where('site_id', $siteId)->first();
        if (!$interviewsCategory) {
            $interviewsCategory = $this->categoryRepository->findOrCreateByName('Interviews', $siteId);
        }
        if ($interviewsCategory) {
            $this->assignPagesToCategory([33, 36, 213, 214], $interviewsCategory->id);
        }

        // Reviews - Albums
        $albumsCategory = Category::where('name', 'Albums')->where('site_id', $siteId)->first();
        if (!$albumsCategory) {
            // Check for 'Album Reviews' which is the common term used for creation
            $albumsCategory = Category::where('name', 'Album Reviews')->where('site_id', $siteId)->first();
            if (!$albumsCategory) {
                $albumsCategory = $this->categoryRepository->findOrCreateByName('Album Reviews', $siteId);
            }
        }
        if ($albumsCategory) {
            $this->assignPagesToCategory([34, 35, 407, 409, 411, 412, 413, 414, 416], $albumsCategory->id);
        }

        // Reviews - Live Shows
        $liveShowsCategory = Category::where('name', 'Live Reviews')->where('site_id', $siteId)->first();
        if (!$liveShowsCategory) {
            $liveShowsCategory = $this->categoryRepository->findOrCreateByName('Live Reviews', $siteId);
        }
        if ($liveShowsCategory) {
            $this->assignPagesToCategory([38, 410], $liveShowsCategory->id);
        }

        // Festivals
        $festivalsCategory = Category::where('name', 'Festival Coverage')->where('site_id', $siteId)->first();
        if (!$festivalsCategory) {
            $festivalsCategory = $this->categoryRepository->findOrCreateByName('Festival Coverage', $siteId);
        }
        if ($festivalsCategory) {
            $this->assignPagesToCategory([35, 213], $festivalsCategory->id);
        }

        // Genres - Rock
        $rockCategory = Category::where('name', 'Rock')->where('site_id', $siteId)->first();
        if ($rockCategory) {
            $this->assignPagesToCategory([212, 416], $rockCategory->id);
        }

        // Genres - Electronic
        $electronicCategory = Category::where('name', 'Electronic')->where('site_id', $siteId)->first();
        if (!$electronicCategory) {
            $electronicCategory = $this->categoryRepository->findOrCreateByName('Electronic', $siteId);
        }
        if ($electronicCategory) {
            $this->assignPagesToCategory([37, 409], $electronicCategory->id);
        }

        // Genres - Urban (Hip-Hop/R&B)
        $urbanCategory = Category::where('name', 'Hip Hop')->where('site_id', $siteId)->first();
        if (!$urbanCategory) {
            $urbanCategory = Category::create([
                'name' => 'Hip Hop',
                'site_id' => $siteId,
                'is_active' => true,
                'slug' => 'hiphop'
            ]);
        }
        if ($urbanCategory) {
            $this->assignPagesToCategory([36, 407, 413], $urbanCategory->id);
        }

        // Culture
        $cultureCategory = Category::where('name', 'Culture')->where('site_id', $siteId)->first();
        if (!$cultureCategory) {
            $cultureCategory = $this->categoryRepository->findOrCreateByName('Culture', $siteId);
        }
        if ($cultureCategory) {
            $this->assignPagesToCategory([37, 211, 215], $cultureCategory->id);
        }

        // --- New Assignments for Menu-defined Categories (Soundwave) ---
        // Page 34 ("Neon Dreams" by SYNTHWAVE) is Electronic/Album Review
        // Page 36 (Zara Quinn, Hip-Hop's New Voice) is Hip-Hop/Interview/Profile
        // Page 33 (Luna Eclipse) is Rock/Interview/Cover Story
        // Page 211 (Shoegaze Revival) is Culture/Feature/Electronic
        // Page 215 (AI Debate) is Culture/Feature/Electronic

        // Genres (from menu)
        // Rock (already assigned)
        $this->assignPagesToCategory([411], $this->categoryRepository->findOrCreateByName('Pop', $siteId)->id); // Billie Eilish - Horizons
        $this->assignPagesToCategory([407], $this->categoryRepository->findOrCreateByName('Hip-Hop', $siteId)->id); // Kendrick Lamar - GNX
        // Electronic (already assigned)
        $this->assignPagesToCategory([212], $this->categoryRepository->findOrCreateByName('Indie', $siteId)->id); // Radiohead's OK Computer
        $this->assignPagesToCategory([414], $this->categoryRepository->findOrCreateByName('Jazz', $siteId)->id); // Kamasi Washington - The Epic Continues
        $this->assignPagesToCategory([214], $this->categoryRepository->findOrCreateByName('R&B', $siteId)->id); // Yaya Bey - Neo-Soul
        $this->assignPagesToCategory([415], $this->categoryRepository->findOrCreateByName('Metal', $siteId)->id); // Gojira - Fortitude

        // Reviews (from menu)
        // Album Reviews (already handled in existing logic, checks for 'Albums' and 'Album Reviews')
        $this->assignPagesToCategory([34], $this->categoryRepository->findOrCreateByName('Track Reviews', $siteId)->id); // Using an album review page for a track review
        // Live Reviews (already assigned)
        // Festival Coverage (already assigned)

        // Features (from menu)
        // Interviews (already assigned)
        $this->assignPagesToCategory([37], $this->categoryRepository->findOrCreateByName('News', $siteId)->id); // Vinyl Renaissance article as a news/industry feature
        $this->assignPagesToCategory([215], $this->categoryRepository->findOrCreateByName('Opinion', $siteId)->id); // AI Debate as opinion piece
        $this->assignPagesToCategory([407], $this->categoryRepository->findOrCreateByName('Charts', $siteId)->id); // Using an arbitrary page, site 37 is music-week, site 7 is soundwave
        $this->assignPagesToCategory([34], $this->categoryRepository->findOrCreateByName('Playlists', $siteId)->id); // Using an album page for playlist content
    }

    private function assignWineChronicleCategories(): void
    {
        $siteId = 21; // Using site_id 21 based on your SQL

        // --- Existing Assignments ---
        // Wine Reviews - By Region - Bordeaux
        $bordeauxCategory = Category::where('name', 'Bordeaux')->where('site_id', $siteId)->first();
        if ($bordeauxCategory) {
            $this->assignPagesToCategory([60, 198], $bordeauxCategory->id);
        }

        // Wine Reviews - By Region - Burgundy
        $burgundyCategory = Category::where('name', 'Burgundy')->where('site_id', $siteId)->first();
        if ($burgundyCategory) {
            $this->assignPagesToCategory([61, 199], $burgundyCategory->id);
        }

        // Wine Reviews - By Region - Champagne
        $champagneCategory = Category::where('name', 'Champagne')->where('site_id', $siteId)->first();
        if ($champagneCategory) {
            $this->assignPagesToCategory([62, 200], $champagneCategory->id);
        }

        // Wine Knowledge - Tasting Guides
        $tastingCategory = $this->findCategoryByName('Tasting Guides', $siteId);
        if ($tastingCategory) {
            $this->assignPagesToCategory([62, 200], $tastingCategory->id);
        }

        // --- New Assignments for Menu-defined Categories (Wine Chronicle) ---
        // Menu is not included, but based on categories.sql (Site 10 - assuming this is the intended site for Wine Chronicle, although the original code uses 21):
        // I will use site 21, as defined in your provided code, and will focus on categories not explicitly assigned in the existing logic.

        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Wine Reviews', $siteId)->id);

        // By Type
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Red Wine', $siteId)->id); // Bordeaux is mostly red
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('White Wine', $siteId)->id); // Burgundy has whites
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Sparkling', $siteId)->id); // Champagne is sparkling
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Rosé', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Dessert Wine', $siteId)->id); // Arbitrary assignment

        // By Region (Bordeaux, Burgundy, Champagne already assigned)
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Napa Valley', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Tuscany', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Rioja', $siteId)->id); // Arbitrary assignment

        // By Vintage
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('2023', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('2022', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('2021', $siteId)->id); // Arbitrary assignment
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('2020', $siteId)->id); // Bordeaux 2020 is Page 198
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Older Vintages', $siteId)->id); // Arbitrary assignment

        // Wine Knowledge
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Wine Knowledge', $siteId)->id); // Champagne article is knowledge
        // Tasting Guides (already assigned)
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Beginner', $siteId)->id); // Tasting guide sub-category
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Intermediate', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Advanced', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Wine Regions', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('France', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Italy', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Spain', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('USA', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Australia', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Chile', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Grape Varieties', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Red Grapes', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('White Grapes', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Food Pairing', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Meat', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Seafood', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Cheese', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Desserts', $siteId)->id);

        // Wine Lifestyle
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Wine Lifestyle', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Wine Travel', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Vineyard Tours', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Wine Routes', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Destinations', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Collecting', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Investment', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Cellaring', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Auction', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Events', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Tastings', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Festivals', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Masterclasses', $siteId)->id);

        // Buying Guides
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Buying Guides', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Best Value', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Premium Selection', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Gift Ideas', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Wine Accessories', $siteId)->id);

        // News & Features
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('News & Features', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Industry News', $siteId)->id);
        $this->assignPagesToCategory([199], $this->categoryRepository->findOrCreateByName('Interviews', $siteId)->id);
        $this->assignPagesToCategory([200], $this->categoryRepository->findOrCreateByName('Opinion', $siteId)->id);
        $this->assignPagesToCategory([198], $this->categoryRepository->findOrCreateByName('Awards', $siteId)->id);
    }

    private function findCategoryByName(string $name, int $siteId): ?Category
    {
        return Category::where('name', $name)
            ->where('site_id', $siteId)
            ->first();
    }

    private function assignMusicWeekCategories(): void
    {
        $siteId = 37;

        // --- Core Assignments based on original seeding and required Categories (Ensuring categories exist) ---

        // 1. News - Breaking News
        $breakingNewsCategory = $this->categoryRepository->findOrCreateByName('Breaking News', $siteId);
        $this->assignPagesToCategory([367, 373], $breakingNewsCategory->id);

        // 2. News - Business News
        $businessNewsCategory = $this->categoryRepository->findOrCreateByName('Business News', $siteId);
        $this->assignPagesToCategory([368, 369, 374], $businessNewsCategory->id);

        // 3. Features - Interviews
        $interviewsCategory = $this->categoryRepository->findOrCreateByName('Interviews', $siteId);
        $this->assignPagesToCategory([372], $interviewsCategory->id);

        // 4. Features - Analysis
        $analysisCategory = $this->categoryRepository->findOrCreateByName('Analysis', $siteId);
        $this->assignPagesToCategory([370, 376], $analysisCategory->id);

        // 5. Sectors - Live Music
        $liveMusicCategory = $this->categoryRepository->findOrCreateByName('Live Music', $siteId);
        $this->assignPagesToCategory([368, 371, 375], $liveMusicCategory->id);

        // 6. Sectors - Recorded Music
        $recordedMusicCategory = $this->categoryRepository->findOrCreateByName('Recorded Music', $siteId);
        $this->assignPagesToCategory([367, 373], $recordedMusicCategory->id);

        // 7. Charts & Data (Matching the menu label 'Charts & Data')
        $chartsAndDataCategory = $this->categoryRepository->findOrCreateByName('Charts & Data', $siteId);
        $this->assignPagesToCategory([373], $chartsAndDataCategory->id);

        // --- Additional Menu Assignments (Ensuring every sub-category has an assigned page) ---
        // Note: Duplicating page assignments across categories where relevant.

        // 8. News - Industry News (Using page 369: AI Music Regulation)
        $industryNewsCategory = $this->categoryRepository->findOrCreateByName('Industry News', $siteId);
        $this->assignPagesToCategory([369], $industryNewsCategory->id);

        // 9. News - Chart News (Using page 373: UK Streaming Hits)
        $chartNewsCategory = $this->categoryRepository->findOrCreateByName('Chart News', $siteId);
        $this->assignPagesToCategory([373], $chartNewsCategory->id);

        // 10. News - UK Music (Using page 371: UK Festival Season Preview)
        $ukMusicCategory = $this->categoryRepository->findOrCreateByName('UK Music', $siteId);
        $this->assignPagesToCategory([371], $ukMusicCategory->id);

        // 11. News - Global Music (Using page 374: Major Labels Q4 Results)
        $globalMusicCategory = $this->categoryRepository->findOrCreateByName('Global Music', $siteId);
        $this->assignPagesToCategory([374], $globalMusicCategory->id);

        // 12. Sectors - Publishing (Using page 372: Spotify UK MD Interview, covers industry aspects)
        $publishingCategory = $this->categoryRepository->findOrCreateByName('Publishing', $siteId);
        $this->assignPagesToCategory([372], $publishingCategory->id);

        // 13. Sectors - Music Tech (Using page 376: AI in Music Production)
        $musicTechCategory = $this->categoryRepository->findOrCreateByName('Music Tech', $siteId);
        $this->assignPagesToCategory([376], $musicTechCategory->id);

        // 14. Sectors - Radio (Using page 373: UK Streaming Hits, a general industry success article)
        $radioCategory = $this->categoryRepository->findOrCreateByName('Radio', $siteId);
        $this->assignPagesToCategory([373], $radioCategory->id);

        // 15. Features - Opinion (Using page 370: Indie Labels Report, an editorial/analysis piece)
        $opinionCategory = $this->categoryRepository->findOrCreateByName('Opinion', $siteId);
        $this->assignPagesToCategory([370], $opinionCategory->id);

        // 16. Features - Reports (Using page 374: Major Labels Q4 Results)
        $reportsCategory = $this->categoryRepository->findOrCreateByName('Reports', $siteId);
        $this->assignPagesToCategory([374], $reportsCategory->id);
    }

    private function assignGamesRadarCategories(): void
    {
        $siteId = 38;

        // --- Existing Assignments ---
        // Reviews - Game Reviews
        $gameReviewsCategory = Category::where('name', 'Game Reviews')->where('site_id', $siteId)->first();
        if ($gameReviewsCategory) {
            $this->assignPagesToCategory([380, 381, 383, 398, 399, 400, 401, 402, 403, 404, 405, 406], $gameReviewsCategory->id);
        }

        // Guides - Walkthroughs
        $walkthroughsCategory = Category::where('name', 'Walkthroughs')->where('site_id', $siteId)->first();
        if ($walkthroughsCategory) {
            $this->assignPagesToCategory([382], $walkthroughsCategory->id);
        }

        // Platforms - PC
        $pcCategory = Category::where('name', 'PC')->where('site_id', $siteId)->first();
        if ($pcCategory) {
            $this->assignPagesToCategory([384, 385], $pcCategory->id);
        }

        // Platforms - Nintendo Switch
        $switchCategory = Category::where('name', 'Nintendo Switch')->where('site_id', $siteId)->first();
        if (!$switchCategory) {
            $switchCategory = $this->categoryRepository->findOrCreateByName('Nintendo Switch', $siteId);
        }
        $this->assignPagesToCategory([383, 398], $switchCategory->id);

        // Genres - RPG
        $rpgCategory = Category::where('name', 'RPG')->where('site_id', $siteId)->first();
        if ($rpgCategory) {
            $this->assignPagesToCategory([380, 400], $rpgCategory->id);
        }

        // Genres - Action
        $actionCategory = Category::where('name', 'Action')->where('site_id', $siteId)->first();
        if ($actionCategory) {
            $this->assignPagesToCategory([383, 398, 401, 402], $actionCategory->id);
        }

        // Genres - Adventure
        $adventureCategory = Category::where('name', 'Adventure')->where('site_id', $siteId)->first();
        if ($adventureCategory) {
            $this->assignPagesToCategory([383, 398, 403], $adventureCategory->id);
        }

        // Genres - Indie
        $indieCategory = Category::where('name', 'Indie')->where('site_id', $siteId)->first();
        if ($indieCategory) {
            $this->assignPagesToCategory([404, 405, 406], $indieCategory->id);
        }

        // --- New Assignments for Menu-defined Categories (GamesRadar+) ---
        // Page 381 (PlayStation 6: Everything We Know) is Hardware/News

        // Reviews (from menu)
        // Game Reviews (already assigned)
        $this->assignPagesToCategory([381], $this->categoryRepository->findOrCreateByName('Hardware Reviews', $siteId)->id);
        $this->assignPagesToCategory([402], $this->categoryRepository->findOrCreateByName('PS5 Reviews', $siteId)->id); // God of War: Ragnarok
        $this->assignPagesToCategory([401], $this->categoryRepository->findOrCreateByName('Xbox Reviews', $siteId)->id); // Hogwarts Legacy (multiplatform)
        $this->assignPagesToCategory([400], $this->categoryRepository->findOrCreateByName('PC Reviews', $siteId)->id); // Baldur's Gate 3 (PC)
        // Switch Reviews (Nintendo Switch is assigned, but adding 'Switch Reviews' as per menu)
        $this->assignPagesToCategory([398], $this->categoryRepository->findOrCreateByName('Switch Reviews', $siteId)->id);

        // Platforms (from menu)
        $this->assignPagesToCategory([402], $this->categoryRepository->findOrCreateByName('PlayStation', $siteId)->id);
        $this->assignPagesToCategory([401], $this->categoryRepository->findOrCreateByName('Xbox', $siteId)->id);
        // Nintendo Switch (already assigned)
        // PC Gaming (PC is assigned, adding 'PC Gaming' as per menu)
        $this->assignPagesToCategory([384], $this->categoryRepository->findOrCreateByName('PC Gaming', $siteId)->id);
        $this->assignPagesToCategory([405], $this->categoryRepository->findOrCreateByName('Mobile', $siteId)->id); // Among Us (mobile game)

        // Guides (from menu)
        // Walkthroughs (already assigned)
        $this->assignPagesToCategory([382], $this->categoryRepository->findOrCreateByName('Tips & Tricks', $siteId)->id); // Elden Ring DLC is a guide/walkthrough
        $this->assignPagesToCategory([384], $this->categoryRepository->findOrCreateByName('How To Guides', $siteId)->id); // PC Build Guide
        $this->assignPagesToCategory([380], $this->categoryRepository->findOrCreateByName('Best Lists', $siteId)->id); // Arbitrary assignment

        // News
        $this->assignPagesToCategory([381], $this->categoryRepository->findOrCreateByName('News', $siteId)->id);

        // Features
        $this->assignPagesToCategory([385], $this->categoryRepository->findOrCreateByName('Features', $siteId)->id); // Last of Us Part III Wishlist
    }

    private function assignHorseAndHoundCategories(): void
    {
        $siteId = 29;

        // --- Existing Assignments ---
        // News - Breaking News
        $breakingNewsCategory = Category::where('name', 'Breaking News')->where('site_id', $siteId)->first();
        if ($breakingNewsCategory) {
            $this->assignPagesToCategory([280, 284, 288], $breakingNewsCategory->id);
        }

        // Disciplines - Eventing
        $eventingCategory = Category::where('name', 'Eventing')->where('site_id', $siteId)->first();
        if ($eventingCategory) {
            $this->assignPagesToCategory([280], $eventingCategory->id);
        }

        // Disciplines - Showjumping
        $showjumpingCategory = Category::where('name', 'Showjumping')->where('site_id', $siteId)->first();
        if ($showjumpingCategory) {
            $this->assignPagesToCategory([284, 285], $showjumpingCategory->id);
        }

        // Disciplines - Dressage
        $dressageCategory = Category::where('name', 'Dressage')->where('site_id', $siteId)->first();
        if ($dressageCategory) {
            $this->assignPagesToCategory([288], $dressageCategory->id);
        }

        // Horse Care - Health & Fitness
        $healthCategory = $this->findCategoryByName('Health & Fitness', $siteId);
        if ($healthCategory) {
            $this->assignPagesToCategory([281], $healthCategory->id);
        }

        // Horse Care - Behaviour & Training
        $trainingCategory = $this->findCategoryByName('Behaviour & Training', $siteId);
        if ($trainingCategory) {
            $this->assignPagesToCategory([283, 285], $trainingCategory->id);
        }

        // Horse Care - Stable Management
        $stableCategory = $this->findCategoryByName('Stable Management', $siteId);
        if ($stableCategory) {
            $this->assignPagesToCategory([286], $stableCategory->id);
        }

        // Buying Guides - Saddles
        $saddlesCategory = Category::where('name', 'Saddles')->where('site_id', $siteId)->first();
        if ($saddlesCategory) {
            $this->assignPagesToCategory([282], $saddlesCategory->id);
        }

        // Buying Guides - Horse Transport
        $transportCategory = $this->findCategoryByName('Horse Transport', $siteId);
        if ($transportCategory) {
            $this->assignPagesToCategory([287], $transportCategory->id);
        }

        // --- New Assignments for Menu-defined Categories (Horse & Hound) ---
        // Page 280 is Eventing, Breaking News
        // Page 281 is Health & Fitness
        // Page 282 is Saddles, Buying Guide
        // Page 283 is Training
        // Page 284 is Showjumping, Breaking News
        // Page 285 is Showjumping, Training
        // Page 287 is Horse Transport

        // News (from menu - extending existing)
        $this->assignPagesToCategory([280, 284, 288], $this->categoryRepository->findOrCreateByName('Competition Reports', $siteId)->id);
        $this->assignPagesToCategory([284], $this->categoryRepository->findOrCreateByName('Show Reports', $siteId)->id);

        // Disciplines (from menu - extending existing)
        $this->assignPagesToCategory([287], $this->categoryRepository->findOrCreateByName('Racing', $siteId)->id);
        $this->assignPagesToCategory([283], $this->categoryRepository->findOrCreateByName('Pony Club', $siteId)->id);
        $this->assignPagesToCategory([281], $this->categoryRepository->findOrCreateByName('Hunting', $siteId)->id);

        // Horse Care (from menu - extending existing)
        $this->assignPagesToCategory([281], $this->categoryRepository->findOrCreateByName('First Aid', $siteId)->id);
        $this->assignPagesToCategory([281], $this->categoryRepository->findOrCreateByName('Nutrition', $siteId)->id);
        $this->assignPagesToCategory([283], $this->categoryRepository->findOrCreateByName('Groundwork', $siteId)->id);
        $this->assignPagesToCategory([285], $this->categoryRepository->findOrCreateByName('Riding Technique', $siteId)->id);
        $this->assignPagesToCategory([286], $this->categoryRepository->findOrCreateByName('Yard Management', $siteId)->id);
        $this->assignPagesToCategory([286], $this->categoryRepository->findOrCreateByName('Equine Security', $siteId)->id);

        // Buying Guides (from menu - extending existing)
        $this->assignPagesToCategory([282], $this->categoryRepository->findOrCreateByName('Tack & Equipment', $siteId)->id);
        $this->assignPagesToCategory([287], $this->categoryRepository->findOrCreateByName('Riding Wear', $siteId)->id);
        $this->assignPagesToCategory([282], $this->categoryRepository->findOrCreateByName('Horse Rugs', $siteId)->id);
    }
}