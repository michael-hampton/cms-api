<?php

?>
@extends('layouts.app')
@css('open-collab.css')
<div class="container">
    <form action="" method="POST" id="editor-form">
        @csrf
        @method('PUT')

        <div class="grid-dashboard">
            <main class="card">
                <input type="text" name="title" value="<?= $page->title ?>"
                       style="font-size: 2rem; border: none; outline: none; margin-bottom: 1rem;"
                       placeholder="Article Title...">
                <textarea name="content" id="main-editor"
                          style="min-height: 500px; border: none; outline: none; resize: vertical;"
                          placeholder="Start writing..."><?= $page->content ?></textarea>
            </main>

            <aside>
                <div class="card">
                    <h3>Publishing Settings</h3>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_paid" id="is_paid" <?= $page->is_paid ? 'checked' : '' ?>>
                            Paid Content (Paywall)
                        </label>
                    </div>

                    <div id="price-field" style="display: <?= $page->is_paid ? 'block' : 'none' ?>">
                        <label>Price (£)</label>
                        <input type="number" name="price" step="0.01" value="<?= $page->price ?>">
                    </div>

                    <hr>
                    <button type="button" onclick="submitUpdate('published')" class="btn btn-primary btn-block">Publish
                        Now
                    </button>
                    <button type="button" onclick="submitUpdate('draft')" class="btn btn-block"
                            style="margin-top:10px;">Save Draft
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
    // JS Logic for Conditional Price Field
    const paidToggle = document.querySelector('#is_paid');
    const priceField = document.querySelector('#price-field');

    paidToggle?.addEventListener('change', () => {
        priceField.style.display = paidToggle.checked ? 'block' : 'none';
    });

    // Simple Dirty-Check Warning
    let isDirty = false;
    document.querySelector('#editor-form').addEventListener('input', () => isDirty = true);
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) e.preventDefault();
    });

    document.getElementById('is_paid').onchange = (e) => {
        document.getElementById('price-input').style.display = e.target.checked ? 'block' : 'none';
    };

    async function submitUpdate(status) {
        const form = document.getElementById('edit-article-form');
        const formData = new FormData(form);

        // Transform price to pence (integer) as required by UpdateContributorPageRequest
        const priceInput = formData.get('price');
        const priceInPence = priceInput ? Math.round(parseFloat(priceInput) * 100) : 0;

        const payload = {
            is_paid: document.getElementById('is_paid').checked,
            price: priceInPence,
            forms: {
                main: {
                    title: formData.get('title'),
                    content: formData.get('content')
                },
                meta: {
                    status: status,
                    // The regex /^[a-z0-9\-]+$/ is enforced on the slug
                    slug: formData.get('slug') || formData.get('title').toLowerCase().replace(/[^a-z0-9-]/g, '-')
                }
            }
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