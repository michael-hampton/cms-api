<?php

namespace App\Database\Seeders;

use App\Framework\Container;
use App\Framework\Database\Seeder\Seeder;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\Site;
use App\Repositories\BlockRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\BlockParserService;

class ComputerWeeklySeeder extends Seeder
{
    private $pageRepository;
    private $blockRepository;
    private $tagRepository;
    private $categoryRepository;
    private $blockParserService;
    private \App\Models\Model $site;
    private \App\Models\Model $menu;

    public function __construct()
    {
        $this->pageRepository = new PageRepository();
        $this->blockRepository = new BlockRepository();
        $this->tagRepository = new TagRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->blockParserService = (new Container())->resolve(BlockParserService::class);

        parent::__construct();
    }

    private function createSite(): void
    {
        $this->site = Site::create([
            'name' => 'TechWeekly',
            'slug' => 'tech-weekly',
            'is_active' => true,
        ]);
    }

    private function createMenu(): void
    {
        $this->menu = Menu::create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'site_id' => $this->site->id,
            'is_active' => true,
        ]);
    }

    public function run(): void
    {
//        $this->createSite();
//        $this->createMenu();
//
//        $this->createTags();
//        $this->createCategories();
//        $this->createCustomFields();
//        $this->createHomepage();
//        $this->createArticles();
//        $this->createAboutPage();
//        $this->createContactPage();
    }

    private function createTags(): void
    {
        $tags = [
            'featured', 'breaking-news', 'exclusive', 'trending',
            'ai', 'machine-learning', 'deep-learning', 'neural-networks',
            'cloud-computing', 'aws', 'azure', 'google-cloud',
            'cybersecurity', 'data-breach', 'ransomware', 'encryption',
            'programming', 'python', 'javascript', 'java', 'rust', 'go',
            'web-development', 'mobile-development', 'devops',
            'blockchain', 'cryptocurrency', 'nft', 'web3',
            'hardware', 'processors', 'gpu', 'storage',
            'software', 'operating-systems', 'databases',
            'networking', '5g', 'iot', 'edge-computing',
            'quantum-computing', 'supercomputers',
            'startups', 'tech-giants', 'acquisitions', 'funding',
            'privacy', 'gdpr', 'regulations',
            'reviews', 'tutorials', 'how-to', 'analysis'
        ];

        foreach ($tags as $tagName) {
            $this->tagRepository->findOrCreateByName($tagName, $this->site->id);;
        }
    }

    private function createCategories(): void
    {
        $categories = [
            'Technology' => [
                'Artificial Intelligence' => ['Machine Learning', 'Neural Networks', 'Computer Vision'],
                'Cloud Computing' => ['AWS', 'Azure', 'Google Cloud'],
                'Hardware' => ['Processors', 'Graphics Cards', 'Storage']
            ],
            'Security' => [
                'Cybersecurity' => ['Threats', 'Solutions', 'Best Practices'],
                'Privacy' => ['Data Protection', 'Regulations', 'Tools']
            ],
            'Development' => [
                'Programming' => ['Languages', 'Frameworks', 'Tools'],
                'Web Development' => ['Frontend', 'Backend', 'Full Stack'],
                'Mobile' => ['iOS', 'Android', 'Cross-platform']
            ],
            'Business' => [
                'Startups' => ['Funding', 'Growth', 'Success Stories'],
                'Enterprise' => ['Solutions', 'Strategies', 'Case Studies'],
                'Industry News' => ['Acquisitions', 'Partnerships', 'Market Trends']
            ],
            'Reviews' => ['Hardware', 'Software', 'Services'],
            'Tutorials' => ['Beginner', 'Intermediate', 'Advanced']
        ];

        $this->createCategoriesRecursively($categories);
    }

    private function createCategoriesRecursively(array $categories, ?int $parentId = null): void
    {
        foreach ($categories as $name => $children) {
            $category = $this->categoryRepository->findOrCreateByName($name, $this->site->id);;
            if ($parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }

            if (is_array($children)) {
                $this->createCategoriesRecursively($children, $category->id);
            }
        }
    }

    private function createCustomFields(): void
    {
        $fields = [
            ['key' => 'author_name', 'name' => 'Author Name', 'type' => 'text'],
            ['key' => 'author_bio', 'name' => 'Author Bio', 'type' => 'textarea'],
            ['key' => 'author_image', 'name' => 'Author Image', 'type' => 'text'],
            ['key' => 'read_time', 'name' => 'Read Time (minutes)', 'type' => 'number'],
            ['key' => 'difficulty_level', 'name' => 'Difficulty Level', 'type' => 'select', 'options' => '{"beginner":"Beginner","intermediate":"Intermediate","advanced":"Advanced","expert":"Expert"}'],
            ['key' => 'code_samples', 'name' => 'Contains Code Samples', 'type' => 'boolean'],
            ['key' => 'video_tutorial', 'name' => 'Video Tutorial URL', 'type' => 'text'],
            ['key' => 'github_repo', 'name' => 'GitHub Repository', 'type' => 'text'],
            ['key' => 'related_technologies', 'name' => 'Related Technologies', 'type' => 'text'],
            ['key' => 'excerpt', 'name' => 'Article Excerpt', 'type' => 'textarea'],
        ];

        foreach ($fields as $field) {
            CustomFieldDefinition::create([
                'key' => $field['key'],
                'name' => $field['name'],
                'type' => $field['type'],
                'is_active' => true,
                'sort_order' => 10,
                'options' => $field['options'] ?? null,
                'site_id' => $this->site->id
            ]);
        }
    }

    private function createHomepage(): void
    {
        $page = Page::create([
            'title' => 'TechWeekly - Your Source for Technology News',
            'page_type' => 'content',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => 'TechWeekly - Technology News, Reviews, and Tutorials',
            'meta_description' => 'Stay updated with the latest technology news, in-depth reviews, programming tutorials, and industry insights.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Home',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $featuredTag = $this->tagRepository->findOrCreateByName('featured', 1);
        $page->tags(true)->attach($featuredTag->id);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Technology News & Insights',
                    'subtitle' => 'Stay ahead with breaking tech news, expert analysis, and comprehensive guides',
                    'ctaText' => 'Latest Articles',
                    'ctaUrl' => '#featured',
                    'secondaryCtaText' => 'Subscribe',
                    'secondaryCtaUrl' => '/subscribe',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'info',
                'data' => [
                    'infoType' => 'info',
                    'description' => '🔥 Breaking: OpenAI announces GPT-5 with revolutionary capabilities. Read our coverage →'
                ],
                'order' => 2
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Featured Stories',
                    'subtitle' => 'Top picks from our editorial team',
                    'level' => 2
                ],
                'order' => 3
            ],
            [
                'type' => 'page_grid',
                'data' => [
                    'title' => '',
                    'layout' => 'grid',
                    'columns' => 3,
                    'showExcerpt' => true,
                    'showImage' => true,
                    'showMeta' => true,
                    'pages' => [
                        [
                            'title' => 'The Rise of Quantum Computing: What It Means for Cybersecurity',
                            'slug' => 'quantum-computing-cybersecurity',
                            'excerpt' => 'As quantum computers become more powerful, they pose both opportunities and threats to modern encryption systems. Here\'s what you need to know.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Quantum Computer'
                            ],
                            'badge' => [
                                'text' => 'Featured',
                                'color' => 'primary'
                            ],
                            'meta' => [
                                'author' => 'Dr. Sarah Chen',
                                'date' => 'March 15, 2025',
                                'readTime' => '12 min read',
                                'category' => 'Security'
                            ]
                        ],
                        [
                            'title' => 'Building Scalable Microservices with Go and Kubernetes',
                            'slug' => 'microservices-go-kubernetes',
                            'excerpt' => 'A comprehensive guide to architecting, developing, and deploying production-ready microservices using Go and Kubernetes.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'Kubernetes Architecture'
                            ],
                            'badge' => [
                                'text' => 'Tutorial',
                                'color' => 'success'
                            ],
                            'meta' => [
                                'author' => 'James Rodriguez',
                                'date' => 'March 14, 2025',
                                'readTime' => '25 min read',
                                'category' => 'Development'
                            ]
                        ],
                        [
                            'title' => 'AI-Powered Code Generation: GitHub Copilot vs Amazon CodeWhisperer',
                            'slug' => 'ai-code-generation-comparison',
                            'excerpt' => 'We put the leading AI coding assistants head-to-head to see which one truly enhances developer productivity.',
                            'image' => [
                                'src' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                                'alt' => 'AI Coding Assistant'
                            ],
                            'badge' => [
                                'text' => 'Review',
                                'color' => 'warning'
                            ],
                            'meta' => [
                                'author' => 'Alex Kumar',
                                'date' => 'March 13, 2025',
                                'readTime' => '15 min read',
                                'category' => 'AI'
                            ]
                        ]
                    ]
                ],
                'order' => 4
            ],
            [
                'type' => 'divider',
                'data' => [
                    'style' => 'solid'
                ],
                'order' => 5
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Latest Technology News',
                    'subtitle' => 'Stay informed with breaking tech updates',
                    'level' => 2
                ],
                'order' => 6
            ],
            [
                'type' => 'list',
                'data' => [
                    'listType' => 'ul',
                    'items' => [
                        'Apple announces M4 chip with revolutionary neural engine capabilities',
                        'Microsoft Azure introduces new serverless container platform',
                        'Google DeepMind achieves breakthrough in protein folding prediction',
                        'Tesla open-sources Full Self-Driving neural network architecture',
                        'Amazon Web Services launches quantum computing service for developers',
                        'Meta unveils next-generation VR headset with eye-tracking and haptics'
                    ]
                ],
                'order' => 7
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Reach',
                    'stats' => [
                        ['number' => '5M+', 'label' => 'Monthly Readers', 'icon' => '📊'],
                        ['number' => '2,500+', 'label' => 'Articles Published', 'icon' => '📝'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '👨‍💻'],
                        ['number' => '100K+', 'label' => 'Newsletter Subscribers', 'icon' => '📧']
                    ]
                ],
                'order' => 8
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => 'Technology is best when it brings people together.',
                    'attribution' => 'Matt Mullenweg, WordPress Founder'
                ],
                'order' => 9
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'subtitle' => 'Get the latest tech news and tutorials delivered weekly',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => false,
                    'showMessage' => false,
                    'submitButtonText' => 'Subscribe',
                    'requireName' => true,
                    'requireEmail' => true
                ],
                'order' => 10
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createArticles(): void
    {
        $articles = [
            [
                'title' => 'The Rise of Quantum Computing: What It Means for Cybersecurity',
                'slug' => 'quantum-computing-cybersecurity',
                'tags' => ['featured', 'quantum-computing', 'cybersecurity', 'encryption'],
                'categories' => ['Security', 'Cybersecurity', 'Threats'],
                'custom_fields' => [
                    'author_name' => 'Dr. Sarah Chen',
                    'author_bio' => 'Dr. Chen is a quantum computing researcher and cybersecurity expert with a PhD from MIT.',
                    'read_time' => 12,
                    'difficulty_level' => 'intermediate',
                    'excerpt' => 'As quantum computers become more powerful, they pose both opportunities and threats to modern encryption systems. Here\'s what you need to know.',
                    'related_technologies' => 'Quantum Computing, Cryptography, Post-Quantum Encryption'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Quantum Computer Hardware',
                            'caption' => 'IBM\'s latest quantum computer with 1,000+ qubits',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Quantum computing has moved from theoretical physics to practical reality. Major tech companies like IBM, Google, and Amazon are racing to build quantum computers powerful enough to solve problems that would take classical computers millions of years.',
                                'But this technological leap comes with a dark side: quantum computers could break most of the encryption that protects our digital world. From bank transactions to government communications, much of our modern infrastructure relies on encryption algorithms that quantum computers could crack in minutes.',
                                'The threat is real enough that organizations worldwide are scrambling to develop "post-quantum cryptography" – encryption methods that can withstand attacks from quantum computers.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Understanding the Quantum Threat',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Traditional computers process information as bits – ones and zeros. Quantum computers use quantum bits, or "qubits," which can exist in multiple states simultaneously thanks to a property called superposition.',
                                'This allows quantum computers to perform certain calculations exponentially faster than classical computers. Specifically, they excel at problems involving factoring large numbers – the mathematical foundation of RSA encryption, which secures most of today\'s internet traffic.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Security experts estimate that a sufficiently powerful quantum computer could break RSA-2048 encryption within 8 hours. Current quantum computers are not yet powerful enough, but they\'re advancing rapidly.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Shor\'s Algorithm: The Encryption Killer',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In 1994, mathematician Peter Shor developed an algorithm that allows quantum computers to factor large numbers efficiently. This discovery sent shockwaves through the cybersecurity community because factoring large primes is the basis of RSA encryption.',
                                'To understand the scale of the threat, consider this: RSA-2048 encryption uses a 2048-bit number that\'s the product of two large primes. Classical computers would need billions of years to factor this number. A quantum computer running Shor\'s algorithm could do it in hours.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Encryption Method', 'Classical Time to Break', 'Quantum Time to Break', 'Status'],
                                ['RSA-2048', 'Billions of years', '~8 hours', 'Vulnerable'],
                                ['ECC-256', 'Trillions of years', '~1 hour', 'Vulnerable'],
                                ['AES-256', 'Effectively unbreakable', 'Centuries', 'Resistant'],
                                ['Lattice-based', 'Effectively unbreakable', 'Effectively unbreakable', 'Quantum-safe']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Post-Quantum Cryptography: The Solution',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The cryptography community isn\'t sitting idle. Researchers have been developing post-quantum cryptographic algorithms that remain secure even against quantum attacks. In 2022, NIST (National Institute of Standards and Technology) announced the first four quantum-resistant cryptographic algorithms to be standardized.',
                                'These algorithms are based on mathematical problems that even quantum computers find difficult to solve, such as lattice-based cryptography, hash-based signatures, and multivariate polynomial cryptography.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'What Organizations Should Do Now',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Inventory all systems using public-key cryptography',
                                'Prioritize systems that handle sensitive data or have long-term security requirements',
                                'Begin testing post-quantum cryptographic algorithms in non-production environments',
                                'Develop a migration timeline for transitioning to quantum-safe encryption',
                                'Stay informed about NIST standards and industry best practices',
                                'Consider "crypto-agility" – designing systems that can easily switch encryption methods'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Timeline Alert',
                            'paragraphs' => [
                                'Security experts recommend beginning the transition to post-quantum cryptography now, even though large-scale quantum computers don\'t yet exist. The reason? "Harvest now, decrypt later" attacks where adversaries collect encrypted data today with the intent to decrypt it once quantum computers become available.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Road Ahead',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'The transition to post-quantum cryptography will be one of the largest infrastructure upgrades in internet history. It won\'t happen overnight – NIST estimates the transition will take 10-15 years.',
                                'But the effort is essential. As quantum computing advances, the cryptographic foundations of our digital world must evolve with it. Organizations that start planning now will be best positioned to maintain security in the quantum era.',
                                'The quantum revolution is coming. The question isn\'t whether to prepare, but whether we\'ll be ready in time.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'The quantum threat to cryptography isn\'t a matter of if, but when. Organizations need to act now to protect their long-term security.',
                            'attribution' => 'Dr. Lily Chen, NIST Mathematician'
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Building Scalable Microservices with Go and Kubernetes',
                'slug' => 'microservices-go-kubernetes',
                'tags' => ['featured', 'programming', 'go', 'kubernetes', 'devops', 'tutorials'],
                'categories' => ['Development', 'Programming', 'Tools'],
                'custom_fields' => [
                    'author_name' => 'James Rodriguez',
                    'author_bio' => 'James is a senior software architect specializing in distributed systems and cloud-native applications.',
                    'read_time' => 25,
                    'difficulty_level' => 'advanced',
                    'excerpt' => 'A comprehensive guide to architecting, developing, and deploying production-ready microservices using Go and Kubernetes.',
                    'code_samples' => true,
                    'github_repo' => 'https://github.com/techweekly/go-microservices-example',
                    'related_technologies' => 'Go, Kubernetes, Docker, gRPC, Prometheus'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'Kubernetes Architecture Diagram',
                            'caption' => 'Microservices architecture on Kubernetes',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Microservices architecture has become the de facto standard for building scalable, maintainable applications. When combined with Go\'s performance and simplicity, and Kubernetes\' powerful orchestration capabilities, you have a robust foundation for production systems.',
                                'In this comprehensive guide, we\'ll walk through building a complete microservices application from scratch. We\'ll cover service design, inter-service communication, deployment strategies, monitoring, and everything else you need for production readiness.',
                                'By the end of this tutorial, you\'ll have a fully functional microservices application running on Kubernetes, complete with service discovery, health checks, monitoring, and auto-scaling.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Why Go and Kubernetes?',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Go (Golang) is an excellent choice for microservices due to its fast compilation, built-in concurrency support, small binary sizes, and excellent standard library. Major companies like Google, Uber, and Netflix use Go for their microservices.',
                                'Kubernetes provides the orchestration layer that makes managing dozens or hundreds of microservices feasible. It handles service discovery, load balancing, scaling, and self-healing automatically.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'Benefit', 'Impact'],
                                ['Go Concurrency', 'Goroutines for lightweight threading', 'Handle thousands of concurrent requests'],
                                ['Small Binaries', 'Minimal container images', 'Faster deployments and less storage'],
                                ['Fast Compilation', 'Quick build times', 'Faster development cycles'],
                                ['K8s Auto-scaling', 'Dynamic resource allocation', 'Cost optimization and performance'],
                                ['Service Mesh', 'Advanced traffic management', 'Canary deployments and A/B testing']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Architecture Overview',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Our example application will be an e-commerce platform with the following microservices:',
                                'Each service will be independently deployable, with its own database, and communicate via gRPC for internal calls and REST for external APIs.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'API Gateway - Entry point for all external requests',
                                'User Service - Authentication and user management',
                                'Product Service - Product catalog and inventory',
                                'Order Service - Order processing and management',
                                'Payment Service - Payment processing integration',
                                'Notification Service - Email and SMS notifications'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Setting Up the Development Environment',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'note',
                            'description' => 'Prerequisites: Go 1.21+, Docker, kubectl, and access to a Kubernetes cluster (minikube for local development)'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Building Your First Service',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Let\'s start with the User Service. We\'ll implement a simple service with health checks, structured logging, and graceful shutdown – all essential for production systems.',
                                'The code structure follows Go best practices with clear separation of concerns: handlers, services, repositories, and models.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Service Communication with gRPC',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'While REST is great for external APIs, gRPC is superior for inter-service communication. It\'s faster, supports streaming, and provides strong typing through Protocol Buffers.',
                                'We\'ll define our service contracts using .proto files and generate Go code automatically. This ensures type safety and makes API changes explicit.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Kubernetes Deployment',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Each microservice gets its own Kubernetes Deployment, Service, and ConfigMap. We\'ll use Helm charts to manage these resources, making deployments consistent and repeatable.',
                                'Key Kubernetes features we\'ll leverage include health checks (liveness and readiness probes), resource limits, horizontal pod autoscaling, and rolling updates.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ol',
                            'schemaType' => 'steps',
                            'items' => [
                                'Create Dockerfile for each microservice with multi-stage builds',
                                'Build and push Docker images to container registry',
                                'Create Kubernetes manifests (Deployment, Service, ConfigMap)',
                                'Configure health checks and resource limits',
                                'Set up Horizontal Pod Autoscaler',
                                'Deploy to Kubernetes cluster',
                                'Verify deployment with kubectl and monitoring tools'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Observability: Logging, Metrics, and Tracing',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Production microservices need comprehensive observability. We\'ll implement structured logging with zerolog, expose Prometheus metrics, and add distributed tracing with OpenTelemetry.',
                                'This gives us complete visibility into system behavior, performance bottlenecks, and error patterns across all services.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'note',
                        'data' => [
                            'title' => 'Pro Tip',
                            'paragraphs' => [
                                'Always include correlation IDs in your logs and traces. This makes debugging distributed systems infinitely easier by allowing you to trace a single request across multiple services.'
                            ],
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Handling Failures Gracefully',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'In distributed systems, failures are inevitable. Services crash, networks partition, and databases go down. Your architecture must handle these scenarios gracefully.',
                                'We implement circuit breakers to prevent cascading failures, retries with exponential backoff, and timeouts on all external calls. The go-resilience library provides excellent patterns for this.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Security Best Practices',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'Use mutual TLS (mTLS) for service-to-service communication',
                                'Implement API authentication with JWT tokens',
                                'Store secrets in Kubernetes Secrets or external secret managers',
                                'Run containers as non-root users',
                                'Use network policies to restrict service communication',
                                'Regularly scan container images for vulnerabilities',
                                'Implement rate limiting to prevent abuse'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Performance Optimization',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Go\'s performance is excellent out of the box, but microservices have unique optimization opportunities. Connection pooling, caching strategies, and efficient serialization can dramatically improve throughput.',
                                'We\'ll implement Redis for distributed caching, use connection pooling for database and gRPC clients, and leverage Go\'s sync.Pool for object reuse. These optimizations can reduce response times by 50% or more.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'Microservices are not a free lunch. They introduce complexity. But done right, they provide scalability and flexibility that monoliths can\'t match.',
                            'attribution' => 'Martin Fowler'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Conclusion',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Building production-ready microservices with Go and Kubernetes requires careful attention to architecture, observability, security, and operational concerns. But the result is a system that can scale to handle millions of requests while remaining maintainable.',
                                'The complete source code for this tutorial is available on GitHub. Fork it, experiment with it, and adapt it to your needs. Happy coding!',
                                'In our next article, we\'ll dive deeper into service mesh implementations with Istio and advanced deployment patterns like canary releases and blue-green deployments.'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'title' => 'AI-Powered Code Generation: GitHub Copilot vs Amazon CodeWhisperer',
                'slug' => 'ai-code-generation-comparison',
                'tags' => ['featured', 'ai', 'machine-learning', 'programming', 'reviews'],
                'categories' => ['Reviews', 'Software'],
                'custom_fields' => [
                    'author_name' => 'Alex Kumar',
                    'author_bio' => 'Alex is a software engineer and AI enthusiast who tests cutting-edge development tools.',
                    'read_time' => 15,
                    'difficulty_level' => 'intermediate',
                    'excerpt' => 'We put the leading AI coding assistants head-to-head to see which one truly enhances developer productivity.'
                ],
                'content' => [
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                            'alt' => 'AI Code Assistant in IDE',
                            'caption' => 'AI-powered code completion in action',
                            'layout' => 'full',
                            'alignment' => 'fullscreen'
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'AI-powered coding assistants have transformed software development. These tools use large language models trained on billions of lines of code to suggest completions, generate functions, and even write entire classes based on comments.',
                                'GitHub Copilot and Amazon CodeWhisperer are the leading contenders in this space. Both promise to boost developer productivity, but which one delivers? We spent three months testing both tools across various programming languages and project types.',
                                'Here\'s our comprehensive comparison based on real-world usage, not just marketing claims.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Contenders',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'table',
                        'data' => [
                            'hasHeader' => true,
                            'rows' => [
                                ['Feature', 'GitHub Copilot', 'Amazon CodeWhisperer'],
                                ['Price', '$10/month (free for students)', 'Free for individual use'],
                                ['IDE Support', 'VS Code, JetBrains, Neovim', 'VS Code, JetBrains, AWS Cloud9'],
                                ['Languages', '40+ languages', '15+ languages'],
                                ['Training Data', 'Public GitHub repos', 'Amazon internal code + open source'],
                                ['Security Scanning', 'Basic', 'Advanced (AWS integration)'],
                                ['Context Awareness', 'Excellent', 'Good']
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Code Quality and Accuracy',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Both tools excel at generating boilerplate code and simple functions. Where they differ is in handling complex logic and domain-specific code.',
                                'GitHub Copilot consistently provided more creative solutions and better understood context from adjacent files. Its suggestions often anticipated what I was trying to accomplish, even with minimal prompting.',
                                'CodeWhisperer shines when working with AWS services. Its suggestions for boto3 code, Lambda functions, and AWS SDK usage were often more accurate and up-to-date than Copilot\'s.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'award',
                        'data' => [
                            'subcategory' => 'Code Quality Winner',
                            'productName' => 'GitHub Copilot',
                            'winner' => true,
                            'rating' => 4.5,
                            'strapline' => 'Superior context awareness and creative solutions'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Security and License Compliance',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'This is where CodeWhisperer pulls ahead. Amazon built security scanning directly into the tool, flagging potential vulnerabilities and license issues in real-time.',
                                'GitHub Copilot recently added security features, but they\'re not as comprehensive. CodeWhisperer will actually refuse to generate code that matches copyrighted material verbatim, while Copilot requires manual checking.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'info',
                        'data' => [
                            'infoType' => 'warning',
                            'description' => 'Always review AI-generated code carefully. Both tools can occasionally suggest insecure patterns or outdated libraries.'
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Performance Impact',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Neither tool significantly impacts IDE performance, but there are differences. Copilot\'s suggestions appear slightly faster, while CodeWhisperer occasionally has a noticeable delay when generating complex code blocks.',
                                'Both tools can be temporarily disabled when you need maximum IDE responsiveness, like during intensive debugging sessions.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'Productivity Gains',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'Over three months, I tracked my productivity metrics with both tools:',
                                'The real value isn\'t just speed – it\'s reducing context switching. Instead of breaking focus to look up API documentation, the AI suggests the correct method signature inline.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'list',
                        'data' => [
                            'listType' => 'ul',
                            'items' => [
                                'GitHub Copilot: 28% faster completion of new features',
                                'CodeWhisperer: 22% faster completion (35% faster for AWS-related code)',
                                'Both tools: 40%+ reduction in time spent on boilerplate',
                                'Test writing: 50%+ faster with both tools'
                            ]
                        ]
                    ],
                    [
                        'type' => 'heading',
                        'data' => [
                            'text' => 'The Verdict',
                            'level' => 2
                        ]
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'paragraphs' => [
                                'GitHub Copilot wins for general-purpose development. Its superior context awareness, broader language support, and creative suggestions make it the better all-around tool.',
                                'However, if you work primarily with AWS services or need robust security scanning, CodeWhisperer is compelling – especially at its free price point.',
                                'The ideal setup? Use both. Copilot as your primary assistant, CodeWhisperer when working with AWS. Most IDEs can run multiple AI assistants, though you\'ll need to manage conflicts.'
                            ]
                        ]
                    ],
                    [
                        'type' => 'quote',
                        'data' => [
                            'text' => 'AI coding assistants are becoming as essential as syntax highlighting. They won\'t replace developers, but developers who use them will replace those who don\'t.',
                            'attribution' => 'Jeff Atwood, Stack Overflow Co-founder'
                        ]
                    ]
                ]
            ]
        ];

        foreach ($articles as $articleData) {
            $this->createArticle($articleData);
        }
    }

    private function createArticle(array $data): void
    {
        $page = Page::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => 'published',
            'page_type' => 'content',
            'meta_title' => $data['title'] . ' - TechWeekly',
            'meta_description' => $data['custom_fields']['excerpt'] ?? '',
            'site_id' => $this->site->id,
        ]);

        foreach ($data['tags'] as $tagName) {
            $tag = $this->tagRepository->findOrCreateByName($tagName, 1);
            $page->tags(true)->attach($tag->id);
        }

        foreach ($data['categories'] as $categoryName) {
            $category = $this->categoryRepository->findOrCreateByName($categoryName, 1);
            $page->categories(true)->attach($category->id);
        }

        foreach ($data['custom_fields'] as $key => $value) {
            $fieldDef = CustomFieldDefinition::where('key', $key)->first();
            if ($fieldDef) {
                PageCustomField::create([
                    'custom_field_definition_id' => $fieldDef->id,
                    'field_value' => (string)$value,
                    'page_id' => $page->id
                ]);
            }
        }

        foreach ($data['content'] as $index => $blockData) {
            $this->blockRepository->create([
                'page_id' => $page->id,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $index + 1
            ]);
        }
    }

    private function createAboutPage(): void
    {
//        $page = Page::create([
//            'title' => 'About TechWeekly',
//            'page_type' => 'content',
//            'slug' => 'about',
//            'status' => 'published',
//            'meta_title' => 'About Us - TechWeekly',
//            'meta_description' => 'Learn about TechWeekly - Your trusted source for technology news, tutorials, and reviews.',
//            'site_id' => $this->site->id,
//        ]);
//
//        MenuItem::create([
//            'label' => 'About',
//            'menu_id' => $this->menu->id,
//            'target_type' => 'page',
//            'target_id' => $page->id,
//            'is_active' => true,
//        ]);

        $page = Page::find(21);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'About TechWeekly',
                    'subtitle' => 'Empowering developers and tech enthusiasts since 2015',
                    'ctaText' => 'Our Team',
                    'ctaUrl' => '#team',
                    'showSearch' => false,
                    'backgroundImage' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80'
                ],
                'order' => 1
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'TechWeekly was founded in 2015 by a group of passionate developers who wanted to create a publication that truly understood the technical community. We\'re not just journalists covering tech – we\'re developers, engineers, and technologists ourselves.',
                        'Our mission is simple: provide accurate, in-depth technical content that helps our readers stay ahead of the curve. Whether you\'re learning a new programming language, evaluating a technology stack, or keeping up with industry trends, we\'ve got you covered.',
                        'Every article is written by experts with hands-on experience. We don\'t just report on technology – we use it, test it, and break it down so you understand not just what\'s new, but why it matters.'
                    ]
                ],
                'order' => 2
            ],
            [
                'type' => 'stats',
                'data' => [
                    'title' => 'Our Impact',
                    'stats' => [
                        ['number' => '5M+', 'label' => 'Monthly Readers', 'icon' => '📊'],
                        ['number' => '2,500+', 'label' => 'Articles Published', 'icon' => '📝'],
                        ['number' => '50+', 'label' => 'Expert Contributors', 'icon' => '👨‍💻'],
                        ['number' => '100K+', 'label' => 'Newsletter Subscribers', 'icon' => '📧']
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'heading',
                'data' => [
                    'text' => 'Our Editorial Team',
                    'subtitle' => 'Meet the experts behind our content',
                    'level' => 2
                ],
                'order' => 4
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Dr. Sarah Chen',
                    'role' => 'Editor-in-Chief',
                    'bio' => 'Dr. Chen holds a PhD in Computer Science from MIT and has over 15 years of experience in quantum computing and cybersecurity research.',
                    'email' => 'sarah@techweekly.com',
                    'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 5
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'James Rodriguez',
                    'role' => 'Senior Technical Editor',
                    'bio' => 'James is a software architect specializing in distributed systems and cloud-native applications with experience at Google and Amazon.',
                    'email' => 'james@techweekly.com',
                    'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 6
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'Alex Kumar',
                    'role' => 'AI & Development Editor',
                    'bio' => 'Alex is a software engineer and AI enthusiast who tests and reviews cutting-edge development tools and frameworks.',
                    'email' => 'alex@techweekly.com',
                    'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'displayType' => 'profile'
                ],
                'order' => 7
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createContactPage(): void
    {
        $page = Page::create([
            'title' => 'Contact TechWeekly',
            'page_type' => 'content',
            'slug' => 'contact',
            'status' => 'published',
            'meta_title' => 'Contact Us - TechWeekly',
            'meta_description' => 'Get in touch with the TechWeekly editorial team.',
            'site_id' => $this->site->id,
        ]);

        MenuItem::create([
            'label' => 'Contact',
            'menu_id' => $this->menu->id,
            'target_type' => 'page',
            'target_id' => $page->id,
            'is_active' => true,
        ]);

        $blocks = [
            [
                'type' => 'hero',
                'data' => [
                    'title' => 'Get In Touch',
                    'subtitle' => 'Questions, feedback, or story ideas? We\'d love to hear from you',
                    'showSearch' => false
                ],
                'order' => 1
            ],
            [
                'type' => 'person',
                'data' => [
                    'name' => 'TechWeekly Editorial',
                    'role' => 'Contact Information',
                    'email' => 'editorial@techweekly.com',
                    'phone' => '+1 (555) 123-4567',
                    'address' => 'TechWeekly Media\n1 Silicon Valley Way\nSan Francisco, CA 94105\n\nOffice Hours:\nMon-Fri: 9AM-5PM PST',
                    'displayType' => 'contact'
                ],
                'order' => 2
            ],
            [
                'type' => 'text',
                'data' => [
                    'paragraphs' => [
                        'We welcome contributions from experienced developers and technologists. If you\'re interested in writing for TechWeekly, please send us your pitch to editorial@techweekly.com.',
                        'For technical support or website issues, please email support@techweekly.com.',
                        'Press inquiries can be directed to press@techweekly.com.'
                    ]
                ],
                'order' => 3
            ],
            [
                'type' => 'contact-form',
                'data' => [
                    'title' => 'Send Us a Message',
                    'showName' => true,
                    'showEmail' => true,
                    'showPhone' => false,
                    'showSubject' => true,
                    'showMessage' => true,
                    'submitButtonText' => 'Send Message',
                    'requireName' => true,
                    'requireEmail' => true,
                    'requireMessage' => true
                ],
                'order' => 4
            ]
        ];

        $this->createBlocksForPage($page->id, $blocks);
    }

    private function createBlocksForPage(int $pageId, array $blocks): void
    {
        foreach ($blocks as $blockData) {
            $this->blockRepository->create([
                'page_id' => $pageId,
                'type' => $blockData['type'],
                'data' => json_encode($blockData['data']),
                'order' => $blockData['order']
            ]);
        }
    }
}