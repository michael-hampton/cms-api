<?php

use App\Framework\Support\SiteContext;

?>
@extends('layouts.app')
@css('open-collab.css')

<div class="container">
    <form id="create-article-form">
        <div class="dashboard-grid">
            <main class="content-card" style="padding: 2rem;">
                <input type="text" name="title" class="editor-title" placeholder="New Article Title...">
                <textarea name="content" class="editor-textarea" placeholder="Start writing your story..."></textarea>
            </main>

            <aside>
                <div class="card">
                    <h3>Publishing Options</h3>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_paid" id="is_paid"> Premium Article</label>
                    </div>
                    <div id="price-input" style="display: none;">
                        <label>Price (£)</label>
                        <input type="number" name="price" step="0.01" value="0.00">
                    </div>
                    <button type="button" onclick="submitCreate('published')" class="btn btn-primary btn-block">Publish
                        Now
                    </button>
                    <button type="button" onclick="submitCreate('draft')" class="btn btn-block"
                            style="margin-top:10px;">Save Draft
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
    document.getElementById('is_paid').onchange = (e) => {
        document.getElementById('price-input').style.display = e.target.checked ? 'block' : 'none';
    };

    async function submitCreate(status) {
        const form = document.getElementById('create-article-form');
        const formData = new FormData(form);

        const priceInput = formData.get('price');
        const priceInPence = priceInput ? Math.round(parseFloat(priceInput) * 100) : 0;

        const payload = {
            site_id: <?= SiteContext::getId() ?>, // Backend requires site_id
            is_paid: document.getElementById('is_paid').checked,
            price: priceInPence,
            // Optional: add price if your backend expects it here,
            // though it's commented out in your Store Request
            forms: {
                main: {
                    title: formData.get('title'),
                    subtitle: "" // Optional
                },
                meta: {
                    status: status, // 'draft' or 'published'
                    slug: formData.get('title').toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '')
                },
                seo: {
                    meta_title: formData.get('title')
                }
            },
            blocks: [], // Empty array to satisfy request
            gallery_slides: []
        };

        const token = '25ff6fc98de5ce11ea726753b21b2f045d3d5b82013e28ecaf927dc2d90d9804'

        const res = await fetch(`/api/<?= $site ?>/open-collab/pages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                // 2. Add the Bearer token here
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (res.ok) {
            // Success: Redirect to the EDIT view now that it has an ID
            window.location.href = `/articles/${data.page.id}/edit`;
        } else {
            alert(data.message || 'Error creating article');
        }
    }
</script>