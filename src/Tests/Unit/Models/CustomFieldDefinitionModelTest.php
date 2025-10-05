<?php

namespace App\Tests\Unit\Models;

use App\Models\CustomFieldDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CustomFieldDefinitionModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateCustomFieldDefinition()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Author Bio',
            'key' => 'author_bio',
            'type' => 'textarea',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(CustomFieldDefinition::class, $field);
        $this->assertEquals('Author Bio', $field->name);
    }

    public function testOptionsAttributeGetterSetter()
    {
        $options = [
            ['label' => 'Option 1', 'value' => 'opt1'],
            ['label' => 'Option 2', 'value' => 'opt2'],
        ];

        $field = CustomFieldDefinition::create([
            'name' => 'Select Field',
            'key' => 'select_field',
            'type' => 'select',
            'is_active' => true,
        ]);

        $field->setOptionsAttribute($options);
        $field->save();

        $fresh = CustomFieldDefinition::find($field->id);
        $this->assertEquals($options, $fresh->getOptionsAttribute());
    }

    public function testValidationRulesAttributeGetterSetter()
    {
        $rules = ['min' => 5, 'max' => 100];

        $field = CustomFieldDefinition::create([
            'name' => 'Text Field',
            'key' => 'text_field',
            'type' => 'text',
            'is_active' => true,
        ]);

        $field->setValidationRulesAttribute($rules);
        $field->save();

        $fresh = CustomFieldDefinition::find($field->id);
        $this->assertEquals($rules, $fresh->getValidationRulesAttribute());
    }

    public function testIsRequired()
    {
        $required = CustomFieldDefinition::create([
            'name' => 'Required Field',
            'key' => 'required_field',
            'type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ]);

        $optional = CustomFieldDefinition::create([
            'name' => 'Optional Field',
            'key' => 'optional_field',
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($required->isRequired());
        $this->assertFalse($optional->isRequired());
    }

    public function testIsSearchable()
    {
        $searchable = CustomFieldDefinition::create([
            'name' => 'Searchable Field',
            'key' => 'searchable_field',
            'type' => 'text',
            'is_searchable' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($searchable->isSearchable());
    }

    public function testIsActive()
    {
        $active = CustomFieldDefinition::create([
            'name' => 'Active Field',
            'key' => 'active_field',
            'type' => 'text',
            'is_active' => true,
        ]);

        $this->assertTrue($active->isActive());
    }

    public function testIsSelectType()
    {
        $select = CustomFieldDefinition::create([
            'name' => 'Select',
            'key' => 'select',
            'type' => 'select',
            'is_active' => true,
        ]);

        $multiSelect = CustomFieldDefinition::create([
            'name' => 'Multi Select',
            'key' => 'multi_select',
            'type' => 'multi_select',
            'is_active' => true,
        ]);

        $text = CustomFieldDefinition::create([
            'name' => 'Text',
            'key' => 'text',
            'type' => 'text',
            'is_active' => true,
        ]);

        $this->assertTrue($select->isSelectType());
        $this->assertTrue($multiSelect->isSelectType());
        $this->assertFalse($text->isSelectType());
    }

    public function testValidateEmailValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Email',
            'key' => 'email',
            'type' => 'email',
            'is_active' => true,
        ]);

        $this->assertTrue($field->validateValue('test@example.com'));
        $this->assertFalse($field->validateValue('invalid-email'));
        $this->assertTrue($field->validateValue('')); // Empty is valid if not required
    }

    public function testValidateUrlValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Website',
            'key' => 'website',
            'type' => 'url',
            'is_active' => true,
        ]);

        $this->assertTrue($field->validateValue('https://example.com'));
        $this->assertFalse($field->validateValue('not-a-url'));
    }

    public function testValidateNumberValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Age',
            'key' => 'age',
            'type' => 'number',
            'is_active' => true,
        ]);

        $this->assertTrue($field->validateValue(25));
        $this->assertTrue($field->validateValue('25'));
        $this->assertFalse($field->validateValue('not-a-number'));
    }

    public function testValidateBooleanValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Featured',
            'key' => 'featured',
            'type' => 'boolean',
            'is_active' => true,
        ]);

        $this->assertTrue($field->validateValue(true));
        $this->assertTrue($field->validateValue(1));
        $this->assertTrue($field->validateValue('1'));
        $this->assertTrue($field->validateValue('true'));
    }

    public function testValidateSelectValue()
    {
        $options = [
            ['label' => 'Red', 'value' => 'red'],
            ['label' => 'Blue', 'value' => 'blue'],
        ];

        $field = CustomFieldDefinition::create([
            'name' => 'Color',
            'key' => 'color',
            'type' => 'select',
            'is_active' => true,
        ]);

        $field->setOptionsAttribute($options);
        $field->save();

        $this->assertTrue($field->validateValue('red'));
        $this->assertFalse($field->validateValue('green'));
    }

    public function testValidateRequiredValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Required',
            'key' => 'required',
            'type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($field->validateValue(''));
        $this->assertFalse($field->validateValue(null));
        $this->assertTrue($field->validateValue('value'));
    }

    public function testGetFormattedValue()
    {
        $field = CustomFieldDefinition::create([
            'name' => 'Number Field',
            'key' => 'number_field',
            'type' => 'number',
            'is_active' => true,
        ]);

        $this->assertEquals(42.0, $field->getFormattedValue('42'));
        $this->assertEquals(42.5, $field->getFormattedValue('42.5'));
    }

    public function testScopeActive()
    {
        CustomFieldDefinition::create(['name' => 'Active', 'key' => 'active', 'type' => 'text', 'is_active' => true]);
        CustomFieldDefinition::create(['name' => 'Inactive', 'key' => 'inactive', 'type' => 'text', 'is_active' => false]);

        $active = CustomFieldDefinition::active()->get();
        $this->assertCount(1, $active);
    }

    public function testScopeRequired()
    {
        CustomFieldDefinition::create(['name' => 'Required', 'key' => 'required', 'type' => 'text', 'is_required' => true, 'is_active' => true]);
        CustomFieldDefinition::create(['name' => 'Optional', 'key' => 'optional', 'type' => 'text', 'is_required' => false, 'is_active' => true]);

        $required = CustomFieldDefinition::required()->get();
        $this->assertCount(1, $required);
    }

    public function testScopeByGroup()
    {
        CustomFieldDefinition::create(['name' => 'Field 1', 'key' => 'field1', 'type' => 'text', 'group_name' => 'personal', 'is_active' => true]);
        CustomFieldDefinition::create(['name' => 'Field 2', 'key' => 'field2', 'type' => 'text', 'group_name' => 'work', 'is_active' => true]);

        $personal = CustomFieldDefinition::byGroup('personal')->get();
        $this->assertCount(1, $personal);
    }

    public function testScopeByKey()
    {
        CustomFieldDefinition::create(['name' => 'Field 1', 'key' => 'unique_key', 'type' => 'text', 'is_active' => true]);

        $field = CustomFieldDefinition::byKey('unique_key')->first();
        $this->assertEquals('Field 1', $field->name);
    }

    public function testScopeOrdered()
    {
        CustomFieldDefinition::create(['name' => 'Z Field', 'key' => 'z', 'type' => 'text', 'sort_order' => 3, 'is_active' => true]);
        CustomFieldDefinition::create(['name' => 'A Field', 'key' => 'a', 'type' => 'text', 'sort_order' => 1, 'is_active' => true]);

        $ordered = CustomFieldDefinition::ordered()->get();
        $this->assertEquals('A Field', $ordered->first()->name);
    }
}