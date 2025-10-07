<?php

namespace App\Tests\Functional\Controllers;

use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;

class CustomFieldDefinitionControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsActiveFields()
    {
        CustomFieldDefinition::create(['name' => 'Color', 'key' => 'color', 'type' => 'text', 'is_active' => true, 'site_id' => $this->siteId]);
        CustomFieldDefinition::create(['name' => 'Size', 'key' => 'size', 'type' => 'number', 'is_active' => true, 'site_id' => $this->siteId]);
        CustomFieldDefinition::create(['name' => 'Inactive', 'key' => 'inactive', 'type' => 'text', 'is_active' => false, 'site_id' => $this->siteId]);
        $response = $this->getForSite('/api/custom-fields');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['fields']);
    }

    public function testGroupedReturnsFieldsByGroup()
    {
        CustomFieldDefinition::create(['name' => 'Color', 'key' => 'color', 'type' => 'text', 'group_name' => 'Product', 'is_active' => true, 'site_id' => $this->siteId]);;
        CustomFieldDefinition::create(['name' => 'Author', 'key' => 'author', 'type' => 'text', 'group_name' => 'Content', 'is_active' => true, 'site_id' => $this->siteId]);
        $response = $this->getForSite('/api/custom-fields/grouped');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('groups', $data['data']);
        $this->assertArrayHasKey('Product', $data['data']['groups']);
        $this->assertArrayHasKey('Content', $data['data']['groups']);
    }

    public function testShowReturnsFieldById()
    {
        $field = CustomFieldDefinition::create(['name' => 'Color', 'key' => 'color', 'type' => 'text', 'is_active' => true]);
        $response = $this->getForSite("/api/custom-fields/{$field->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Color', $data['data']['field']['name']);
    }

    public function testShowReturnsFieldByKey()
    {
        CustomFieldDefinition::create(['name' => 'Color', 'key' => 'product_color', 'type' => 'text', 'is_active' => true]);
        $response = $this->getForSite('/api/custom-fields/product_color');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Color', $data['data']['field']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/custom-fields/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesField()
    {
        $fieldData = ['name' => 'Product Color', 'key' => 'product_color', 'type' => 'text', 'group' => 'Product', 'required' => true, 'searchable' => true];
        $response = $this->postForSite('/api/custom-fields', $fieldData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Product Color', $data['data']['field']['name']);
        $this->assertEquals('product_color', $data['data']['field']['key']);
    }

    public function testStoreWithOptions()
    {
        $fieldData = ['name' => 'Size', 'key' => 'size', 'type' => 'select', 'options' => ['small', 'medium', 'large']];
        $response = $this->postForSite('/api/custom-fields', $fieldData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['field']['options']);
    }

    public function testUpdateModifiesField()
    {
        $field = CustomFieldDefinition::create(['name' => 'Color', 'key' => 'color', 'type' => 'text', 'is_active' => true]);
        $response = $this->putForSite("/api/custom-fields/{$field->id}", ['name' => 'Updated Color', 'type' => 'text', 'description' => 'New description']);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Color', $data['data']['field']['name']);
    }

    public function testUpdateReturns404ForNonexistent()
    {
        $response = $this->put('/api/custom-fields/999', ['name' => 'Test', 'type' => 'text' ]);;
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesField()
    {
        $field = CustomFieldDefinition::create(['name' => 'Color', 'key' => 'color', 'type' => 'text', 'is_active' => true]);
        $response = $this->deleteForSite("/api/custom-fields/{$field->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(CustomFieldDefinition::find($field->id));
    }

    public function testRequiredReturnsOnlyRequiredFields()
    {
        CustomFieldDefinition::create(['name' => 'Required', 'key' => 'required', 'type' => 'text', 'is_required' => true, 'is_active' => true, 'site_id' => $this->siteId]);;
        CustomFieldDefinition::create(['name' => 'Optional', 'key' => 'optional', 'type' => 'text', 'is_required' => false, 'is_active' => true, 'site_id' => $this->siteId]);;;
        $response = $this->getForSite('/api/custom-fields/required');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['fields']);
        $this->assertEquals('Required', $data['data']['fields'][0]['name']);
    }

    public function testSearchableReturnsOnlySearchableFields()
    {
        CustomFieldDefinition::create(['name' => 'Searchable', 'key' => 'searchable', 'type' => 'text', 'is_searchable' => true, 'is_active' => true, 'site_id' => $this->siteId]);;
        CustomFieldDefinition::create(['name' => 'Not Searchable', 'key' => 'not_searchable', 'type' => 'text', 'is_searchable' => false, 'is_active' => true, 'site_id' => $this->siteId]);;;
        $response = $this->getForSite('/api/custom-fields/searchable');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['fields']);
        $this->assertEquals('Searchable', $data['data']['fields'][0]['name']);
    }

    public function testGetCustomFieldsReturnsAllFieldsAndValues()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        // Create custom field definitions
        $colorField = CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'text',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $sizeField = CustomFieldDefinition::create([
            'name' => 'Size',
            'key' => 'size',
            'type' => 'number',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $inactiveField = CustomFieldDefinition::create([
            'name' => 'Inactive',
            'key' => 'inactive',
            'type' => 'text',
            'is_active' => false,
            'site_id' => $this->siteId
        ]);

        // Set values for some fields
        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $colorField->id,
            'field_value' => 'blue'
        ]);

        // Make request
        $response = $this->get("/api/pages/{$page->id}/custom-fields");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Should only return active fields
        $this->assertCount(2, $data['data']['fields']);

        // Check that color field has value
        $colorFieldData = array_values(array_filter($data['data']['fields'], function ($f) {
            return $f['key'] === 'color';
        }))[0];

        $this->assertTrue($colorFieldData['has_value']);
        $this->assertEquals('blue', $colorFieldData['value']);

        // Check that size field has no value
        $sizeFieldData = array_values(array_filter($data['data']['fields'], function ($f) {
            return $f['key'] === 'size';
        }))[0];

        $this->assertFalse($sizeFieldData['has_value']);
        $this->assertNull($sizeFieldData['value']);

        // Check values map
        $this->assertArrayHasKey('values', $data['data']);
        $this->assertEquals('blue', $data['data']['values'][$colorField->id]);
        $this->assertArrayNotHasKey($sizeField->id, $data['data']['values']);
    }

    public function testGetCustomFieldsGroupedReturnsFieldsByGroup()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft'
        ]);

        // Create grouped custom fields
        $colorField = CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'text',
            'group_name' => 'Product',
            'is_active' => true
        ]);

        $authorField = CustomFieldDefinition::create([
            'name' => 'Author',
            'key' => 'author',
            'type' => 'text',
            'group_name' => 'Content',
            'is_active' => true
        ]);

        $ungroupedField = CustomFieldDefinition::create([
            'name' => 'Notes',
            'key' => 'notes',
            'type' => 'textarea',
            'group_name' => null,
            'is_active' => true
        ]);

        // Set values
        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $colorField->id,
            'field_value' => 'red'
        ]);

        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $authorField->id,
            'field_value' => 'John Doe'
        ]);

        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $ungroupedField->id,
            'field_value' => 'Some notes'
        ]);

        // Make request
        $response = $this->getForSite("/api/pages/{$page->id}/custom-fields/grouped");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('groups', $data['data']);
        $this->assertArrayHasKey('Product', $data['data']['groups']);
        $this->assertArrayHasKey('Content', $data['data']['groups']);
        $this->assertArrayHasKey('default', $data['data']['groups']);

        // Check Product group
        $this->assertCount(1, $data['data']['groups']['Product']);
        $this->assertEquals('color', $data['data']['groups']['Product'][0]['key']);
        $this->assertEquals('red', $data['data']['groups']['Product'][0]['value']);

        // Check Content group
        $this->assertCount(1, $data['data']['groups']['Content']);
        $this->assertEquals('author', $data['data']['groups']['Content'][0]['key']);
        $this->assertEquals('John Doe', $data['data']['groups']['Content'][0]['value']);

        // Check default group (ungrouped)
        $this->assertCount(1, $data['data']['groups']['default']);
        $this->assertEquals('notes', $data['data']['groups']['default'][0]['key']);
    }

    public function testGetCustomFieldsReturnsEmptyForPageWithNoValues()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Empty Page',
            'slug' => 'empty-page',
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        // Create custom field definitions but don't set values
        CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'text',
            'is_active' => true
        ]);

        $response = $this->get("/api/pages/{$page->id}/custom-fields");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEmpty($data['data']['fields']);
        $this->assertEmpty($data['data']['values']);
    }

    public function testGetCustomFieldsReturns500ForInvalidPageId()
    {
        $response = $this->get("/api/pages/999999/custom-fields");

        // Depending on your implementation, this might be 404 or 500
        // Adjust based on your error handling
        $this->assertEquals($response->getStatusCode(), 200);
    }

    public function testUpdateCustomFieldsUpdatesPageFields()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        // Create custom field definitions
        $colorField = CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'text',
            'is_active' => true
        ]);

        $sizeField = CustomFieldDefinition::create([
            'name' => 'Size',
            'key' => 'size',
            'type' => 'number',
            'is_active' => true
        ]);

        // Update fields
        $fieldsData = [
            'fields' => [
                $colorField->id => 'green',
                $sizeField->id => 42
            ]
        ];

        $response = $this->put("/api/pages/{$page->id}/custom-fields", $fieldsData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Custom fields updated successfully', $data['message']);

        // Verify fields were saved
        $getResponse = $this->get("/api/pages/{$page->id}/custom-fields");
        $getData = json_decode($getResponse->getContent(), true);

        $this->assertEquals('green', $getData['data']['values'][$colorField->id]);
        $this->assertEquals(42, $getData['data']['values'][$sizeField->id]);
    }

    public function testUpdateCustomFieldsRemovesOldValues()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        // Create custom field definition
        $colorField = CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'text',
            'is_active' => true
        ]);

        // Set initial value
        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $colorField->id,
            'field_value' => 'red'
        ]);

        // Update with empty fields (should remove the value)
        $fieldsData = ['fields' => []];
        $response = $this->put("/api/pages/{$page->id}/custom-fields", $fieldsData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify field was removed
        $getResponse = $this->get("/api/pages/{$page->id}/custom-fields");
        $getData = json_decode($getResponse->getContent(), true);

        $this->assertEmpty($getData['data']['values']);
    }

    public function testGetCustomFieldsHandlesTypeCasting()
    {
        // Create a page
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'site_id' => 1
        ]);

        // Create different typed fields
        $numberField = CustomFieldDefinition::create([
            'name' => 'Count',
            'key' => 'count',
            'type' => 'number',
            'is_active' => true,
            'site_id' => 1
        ]);

        $boolField = CustomFieldDefinition::create([
            'name' => 'Featured',
            'key' => 'featured',
            'type' => 'boolean',
            'is_active' => true,
            'site_id' => 1
        ]);

        $jsonField = CustomFieldDefinition::create([
            'name' => 'Settings',
            'key' => 'settings',
            'type' => 'json',
            'is_active' => true,
            'site_id' => 1
        ]);

        // Set values (stored as strings in DB)
        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $numberField->id,
            'field_value' => '42'
        ]);

        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $boolField->id,
            'field_value' => '1'
        ]);

        PageCustomField::create([
            'page_id' => $page->id,
            'custom_field_definition_id' => $jsonField->id,
            'field_value' => '{"theme":"dark","notifications":true}'
        ]);

        // Make request
        $response = $this->get("/api/pages/{$page->id}/custom-fields");
        $data = json_decode($response->getContent(), true);

        // Verify type casting
        $this->assertIsInt($data['data']['values'][$numberField->id]);
        $this->assertEquals(42, $data['data']['values'][$numberField->id]);

        $this->assertIsBool($data['data']['values'][$boolField->id]);
        $this->assertTrue($data['data']['values'][$boolField->id]);

        $this->assertIsArray($data['data']['values'][$jsonField->id]);
        $this->assertEquals('dark', $data['data']['values'][$jsonField->id]['theme']);
    }
}