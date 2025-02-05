<?php

namespace SilverStripe\Forms\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Tests\DatetimeFieldTest\Model;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\FieldType\DBDatetime;

class DatetimeFieldTest extends SapphireTest
{
    protected $timezone = null;

    protected function setUp(): void
    {
        parent::setUp();
        i18n::set_locale('en_NZ');
        // Fix now to prevent race conditions
        DBDatetime::set_mock_now('2010-04-04');
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        DBDatetime::clear_mock_now();
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    public function testFormSaveInto()
    {
        $dateTimeField = new DatetimeField('MyDatetime');
        $form = $this->getMockForm();
        $form->Fields()->push($dateTimeField);

        $dateTimeField->setSubmittedValue('2003-03-29T23:59:38');
        $validator = new RequiredFields();
        $this->assertTrue($dateTimeField->validate($validator));
        $m = new Model();
        $form->saveInto($m);
        $this->assertEquals('2003-03-29 23:59:38', $m->MyDatetime);
    }

    public function testFormSaveIntoLocalised()
    {
        $dateTimeField = new DatetimeField('MyDatetime');
        $dateTimeField
            ->setHTML5(false)
            ->setLocale('en_NZ');

        $form = $this->getMockForm();
        $form->Fields()->push($dateTimeField);

        // en_NZ standard format
        $dateTimeField->setSubmittedValue('29/03/2003 11:59:38 pm');
        $validator = new RequiredFields();
        $this->assertTrue($dateTimeField->validate($validator));
        $m = new Model();
        $form->saveInto($m);
        $this->assertEquals('2003-03-29 23:59:38', $m->MyDatetime);
    }

    public function testDataValue()
    {
        $f = new DatetimeField('Datetime');
        $this->assertEquals(null, $f->dataValue(), 'Empty field');

        $f = new DatetimeField('Datetime', null, '2003-03-29 23:59:38');
        $this->assertEquals('2003-03-29 23:59:38', $f->dataValue(), 'From date/time string');
    }

    public function testDataValueWithTimezone()
    {
        // Berlin and Auckland have 12h time difference in northern hemisphere winter
        date_default_timezone_set('Europe/Berlin');

        $f = new DatetimeField('Datetime');
        $f->setTimezone('Pacific/Auckland');
        $f->setSubmittedValue('2003-01-30T23:59:38'); // frontend timezone (Auckland)
        $this->assertEquals('2003-01-30 11:59:38', $f->dataValue()); // server timezone (Berlin)
    }

    public function testSetSubmittedValueNull()
    {
        $field = new DatetimeField('Datetime');
        $field->setSubmittedValue(false);
        $this->assertNull($field->Value());
    }

    public function testConstructorWithoutArgs()
    {
        $f = new DatetimeField('Datetime');
        $this->assertEquals($f->dataValue(), null);
    }

    public function testConstructorWithLocalizedDateSetsNullValue()
    {
        $f = new DatetimeField('Datetime', 'Datetime', '29/03/2003 23:59:38');
        $this->assertNull($f->Value());
    }

    public function testConstructorWithIsoDate()
    {
        // used by Form->loadDataFrom()
        $f = new DatetimeField('Datetime', 'Datetime', '2003-03-29 23:59:38');
        $this->assertEquals($f->dataValue(), '2003-03-29 23:59:38');
    }

    public function testSetValueWithDateTimeString()
    {
        $f = new DatetimeField('Datetime', 'Datetime');
        $f->setValue('2003-03-29 23:59:38');
        $this->assertEquals('2003-03-29 23:59:38', $f->dataValue(), 'Accepts ISO');

        $f = new DatetimeField('Datetime', 'Datetime');
        $f->setValue('2003-03-29T23:59:38');
        $this->assertEquals('2003-03-29 23:59:38', $f->dataValue(), 'Accepts normalised ISO');
    }

    public function testSubmittedValue()
    {
        $datetimeField = new DatetimeField('Datetime', 'Datetime');
        $datetimeField->setSubmittedValue('2003-03-29 23:00:00');
        $this->assertEquals($datetimeField->dataValue(), '2003-03-29 23:00:00');

        $datetimeField = new DatetimeField('Datetime', 'Datetime');
        $datetimeField->setSubmittedValue('2003-03-29T23:00:00');
        $this->assertEquals($datetimeField->dataValue(), '2003-03-29 23:00:00', 'Normalised ISO');
    }

    public function testSetValueWithLocalised()
    {
        $datetimeField = new DatetimeField('Datetime', 'Datetime');

        $datetimeField
            ->setHTML5(false)
            ->setLocale('en_NZ');

        $datetimeField->setSubmittedValue('29/03/2003 11:00:00 pm');
        $this->assertEquals($datetimeField->dataValue(), '2003-03-29 23:00:00');

        // Some localisation packages exclude the ',' in default medium format
        $this->assertMatchesRegularExpression(
            '#29/03/2003(,)? 11:00:00 (PM|pm)#',
            $datetimeField->Value(),
            'User value is formatted, and in user timezone'
        );
    }

    public function testValidate()
    {
        $field = new DatetimeField('Datetime', 'Datetime', '2003-03-29 23:59:38');
        $this->assertTrue($field->validate(new RequiredFields()));

        $field = new DatetimeField('Datetime', 'Datetime', '2003-03-29T23:59:38');
        $this->assertTrue($field->validate(new RequiredFields()), 'Normalised ISO');

        $field = new DatetimeField('Datetime', 'Datetime', '2003-03-29');
        $this->assertFalse($field->validate(new RequiredFields()), 'Leaving out time');

        $field = (new DatetimeField('Datetime', 'Datetime'))
            ->setSubmittedValue('2003-03-29T00:00');
        $this->assertTrue($field->validate(new RequiredFields()), 'Leaving out seconds (like many browsers)');

        $field = new DatetimeField('Datetime', 'Datetime', 'wrong');
        $this->assertFalse($field->validate(new RequiredFields()));

        $field = new DatetimeField('Datetime', 'Datetime', false);
        $this->assertTrue($field->validate(new RequiredFields()));
    }

    public function testSetMinDate()
    {
        $f = (new DatetimeField('Datetime'))->setMinDatetime('2009-03-31T23:00:00');
        $this->assertEquals($f->getMinDatetime(), '2009-03-31 23:00:00', 'Retains ISO');

        $f = (new DatetimeField('Datetime'))->setMinDatetime('2009-03-31 23:00:00');
        $this->assertEquals($f->getMinDatetime(), '2009-03-31 23:00:00', 'Converts normalised ISO to ISO');

        $f = (new DatetimeField('Datetime'))->setMinDatetime('invalid');
        $this->assertNull($f->getMinDatetime(), 'Ignores invalid values');
    }

    public function testSetMaxDate()
    {
        $f = (new DatetimeField('Datetime'))->setMaxDatetime('2009-03-31T23:00:00');
        $this->assertEquals($f->getMaxDatetime(), '2009-03-31 23:00:00', 'Retains ISO');

        $f = (new DatetimeField('Datetime'))->setMaxDatetime('2009-03-31 23:00:00');
        $this->assertEquals($f->getMaxDatetime(), '2009-03-31 23:00:00', 'Converts normalised ISO to ISO');

        $f = (new DatetimeField('Datetime'))->setMaxDatetime('invalid');
        $this->assertNull($f->getMaxDatetime(), 'Ignores invalid values');
    }

    public function testValidateMinDate()
    {
        $dateField = new DatetimeField('Datetime');
        $dateField->setMinDatetime('2009-03-31 23:00:00');
        $dateField->setValue('2009-03-31 23:00:01');
        $this->assertTrue($dateField->validate(new RequiredFields()), 'Time above min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setMinDatetime('2009-03-31 23:00:00');
        $dateField->setValue('2009-03-31 22:00:00');
        $this->assertFalse($dateField->validate(new RequiredFields()), 'Time below min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setMinDatetime('2009-03-31 23:00:00');
        $dateField->setValue('2009-03-31 23:00:00');
        $this->assertTrue($dateField->validate(new RequiredFields()), 'Date and time matching min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setMinDatetime('2009-03-31 23:00:00');
        $dateField->setValue('2008-03-31 23:00:00');
        $this->assertFalse($dateField->validate(new RequiredFields()), 'Date below min datetime');
    }

    public function testValidateMinDateWithSubmittedValueAndTimezone()
    {
        // Berlin and Auckland have 12h time difference in northern hemisphere winter
        date_default_timezone_set('Europe/Berlin');

        $dateField = new DatetimeField('Datetime');
        $dateField->setTimezone('Pacific/Auckland');
        $dateField->setMinDatetime('2009-01-30 23:00:00'); // server timezone (Berlin)
        $dateField->setSubmittedValue('2009-01-31T11:00:01'); // frontend timezone (Auckland)
        $this->assertTrue($dateField->validate(new RequiredFields()), 'Time above min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setTimezone('Pacific/Auckland');
        $dateField->setMinDatetime('2009-01-30 23:00:00');
        $dateField->setSubmittedValue('2009-01-31T10:00:00');
        $this->assertFalse($dateField->validate(new RequiredFields()), 'Time below min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setTimezone('Pacific/Auckland');
        $dateField->setMinDatetime('2009-01-30 23:00:00');
        $dateField->setSubmittedValue('2009-01-31T11:00:00');
        $this->assertTrue($dateField->validate(new RequiredFields()), 'Date and time matching min datetime');

        $dateField = new DatetimeField('Datetime');
        $dateField->setTimezone('Pacific/Auckland');
        $dateField->setMinDatetime('2009-01-30 23:00:00');
        $dateField->setSubmittedValue('2008-01-31T11:00:00');
        $this->assertFalse($dateField->validate(new RequiredFields()), 'Date below min datetime');
    }

    public function testValidateMinDateStrtotime()
    {
        $f = new DatetimeField('Datetime');
        $f->setMinDatetime('-7 days');
        $f->setValue(strftime('%Y-%m-%d %T', strtotime('-8 days', DBDatetime::now()->getTimestamp())));
        $this->assertFalse($f->validate(new RequiredFields()), 'Date below min datetime, with strtotime');

        $f = new DatetimeField('Datetime');
        $f->setMinDatetime('-7 days');
        $f->setValue(strftime('%Y-%m-%d %T', strtotime('-7 days', DBDatetime::now()->getTimestamp())));
        $this->assertTrue($f->validate(new RequiredFields()), 'Date matching min datetime, with strtotime');
    }

    public function testValidateMaxDateStrtotime()
    {
        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('7 days');
        $f->setValue(strftime('%Y-%m-%d %T', strtotime('8 days', DBDatetime::now()->getTimestamp())));
        $this->assertFalse($f->validate(new RequiredFields()), 'Date above max date, with strtotime');

        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('7 days');
        $f->setValue(strftime('%Y-%m-%d %T', strtotime('7 days', DBDatetime::now()->getTimestamp())));
        $this->assertTrue($f->validate(new RequiredFields()), 'Date matching max date, with strtotime');
    }

    public function testValidateMaxDate()
    {
        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('2009-03-31 23:00:00');
        $f->setValue('2009-03-31 22:00:00');
        $this->assertTrue($f->validate(new RequiredFields()), 'Time below max datetime');

        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('2009-03-31 23:00:00');
        $f->setValue('2010-03-31 23:00:01');
        $this->assertFalse($f->validate(new RequiredFields()), 'Time above max datetime');

        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('2009-03-31 23:00:00');
        $f->setValue('2009-03-31 23:00:00');
        $this->assertTrue($f->validate(new RequiredFields()), 'Date and time matching max datetime');

        $f = new DatetimeField('Datetime');
        $f->setMaxDatetime('2009-03-31 23:00:00');
        $f->setValue('2010-03-31 23:00:00');
        $this->assertFalse($f->validate(new RequiredFields()), 'Date above max datetime');
    }

    public function testValidateMaxDateWithSubmittedValueAndTimezone()
    {
        // Berlin and Auckland have 12h time difference in northern hemisphere winter
        date_default_timezone_set('Europe/Berlin');

        $f = new DatetimeField('Datetime');
        $f->setTimezone('Pacific/Auckland');
        $f->setMaxDatetime('2009-01-31 23:00:00'); // server timezone (Berlin)
        $f->setSubmittedValue('2009-01-31T10:00:00'); // frontend timezone (Auckland)
        $this->assertTrue($f->validate(new RequiredFields()), 'Time below max datetime');

        $f = new DatetimeField('Datetime');
        $f->setTimezone('Pacific/Auckland');
        $f->setMaxDatetime('2009-01-31 23:00:00');
        $f->setSubmittedValue('2010-01-31T11:00:01');
        $this->assertFalse($f->validate(new RequiredFields()), 'Time above max datetime');

        $f = new DatetimeField('Datetime');
        $f->setTimezone('Pacific/Auckland');
        $f->setMaxDatetime('2009-01-31 23:00:00');
        $f->setSubmittedValue('2009-01-31T11:00:00');
        $this->assertTrue($f->validate(new RequiredFields()), 'Date and time matching max datetime');

        $f = new DatetimeField('Datetime');
        $f->setTimezone('Pacific/Auckland');
        $f->setMaxDatetime('2009-01-31 23:00:00');
        $f->setSubmittedValue('2010-01-31T11:00:00');
        $this->assertFalse($f->validate(new RequiredFields()), 'Date above max datetime');
    }

    public function testTimezoneSetValueLocalised()
    {
        date_default_timezone_set('Europe/Berlin');
        // Berlin and Auckland have 12h time difference in northern hemisphere winter
        $datetimeField = new DatetimeField('Datetime', 'Datetime');

        $datetimeField
            ->setHTML5(false)
            ->setDatetimeFormat('dd/MM/y HH:mm:ss');

        $datetimeField->setTimezone('Pacific/Auckland');
        $datetimeField->setValue('2003-12-24 23:59:59');
        $this->assertEquals(
            '25/12/2003 11:59:59',
            $datetimeField->Value(),
            'User value is formatted, and in user timezone'
        );

        $this->assertEquals(
            '2003-12-24 23:59:59',
            $datetimeField->dataValue(),
            'Data value is in ISO format, and in server timezone'
        );
    }

    public function testTimezoneSetValueWithHtml5()
    {
        date_default_timezone_set('Europe/Berlin');
        // Berlin and Auckland have 12h time difference in northern hemisphere winter
        $datetimeField = new DatetimeField('Datetime', 'Datetime');

        $datetimeField->setTimezone('Pacific/Auckland');
        $datetimeField->setValue('2003-12-24 23:59:59');
        $this->assertEquals(
            '2003-12-25T11:59:59',
            $datetimeField->Value(),
            'User value is in normalised ISO format and in user timezone'
        );

        $this->assertEquals(
            '2003-12-24 23:59:59',
            $datetimeField->dataValue(),
            'Data value is in ISO format, and in server timezone'
        );
    }

    public function testTimezoneSetSubmittedValueLocalised()
    {
        date_default_timezone_set('Europe/Berlin');
        // Berlin and Auckland have 12h time difference in northern hemisphere summer, but Berlin and Moscow only 2h.
        $datetimeField = new DatetimeField('Datetime', 'Datetime');

        $datetimeField
            ->setHTML5(false)
            ->setLocale('en_NZ');

        $datetimeField->setTimezone('Europe/Moscow');
        // pass in default format, at user time (Moscow)
        $datetimeField->setSubmittedValue('24/06/2003 11:59:59 pm');
        $this->assertTrue($datetimeField->validate(new RequiredFields()));
        $this->assertEquals('2003-06-24 21:59:59', $datetimeField->dataValue(), 'Data value matches server timezone');
    }

    public function testGetName()
    {
        $field = new DatetimeField('Datetime');

        $this->assertEquals('Datetime', $field->getName());
    }

    public function testSetName()
    {
        $field = new DatetimeField('Datetime', 'Datetime');
        $field->setName('CustomDatetime');
        $this->assertEquals('CustomDatetime', $field->getName());
    }

    public function testSchemaDataDefaultsIncludesMinMax()
    {
        $field = new DatetimeField('Datetime');
        $field->setMinDatetime('2009-03-31 23:00:00');
        $field->setMaxDatetime('2010-03-31 23:00:00');
        $defaults = $field->getSchemaDataDefaults();
        $this->assertEquals($defaults['data']['min'], '2009-03-31T23:00:00');
        $this->assertEquals($defaults['data']['max'], '2010-03-31T23:00:00');
    }

    public function testSchemaDataDefaultsAdjustsMinMaxToTimezone()
    {
        date_default_timezone_set('Europe/Berlin');
        // Berlin and Auckland have 12h time difference in northern hemisphere summer, but Berlin and Moscow only 2h.

        $field = new DatetimeField('Datetime');
        $field->setTimezone('Pacific/Auckland');
        $field->setMinDatetime('2009-01-31 11:00:00'); // server timezone
        $field->setMaxDatetime('2010-01-31 11:00:00'); // server timezone
        $defaults = $field->getSchemaDataDefaults();
        $this->assertEquals($defaults['data']['min'], '2009-01-31T23:00:00'); // frontend timezone
        $this->assertEquals($defaults['data']['max'], '2010-01-31T23:00:00'); // frontend timezone
    }

    public function testAttributesIncludesMinMax()
    {
        $field = new DatetimeField('Datetime');
        $field->setMinDatetime('2009-03-31 23:00:00');
        $field->setMaxDatetime('2010-03-31 23:00:00');
        $attrs = $field->getAttributes();
        $this->assertEquals($attrs['min'], '2009-03-31T23:00:00');
        $this->assertEquals($attrs['max'], '2010-03-31T23:00:00');
    }

    public function testAttributesAdjustsMinMaxToTimezone()
    {
        date_default_timezone_set('Europe/Berlin');
        // Berlin and Auckland have 12h time difference in northern hemisphere summer, but Berlin and Moscow only 2h.

        $field = new DatetimeField('Datetime');
        $field->setTimezone('Pacific/Auckland');
        $field->setMinDatetime('2009-01-31 11:00:00'); // server timezone
        $field->setMaxDatetime('2010-01-31 11:00:00'); // server timezone
        $attrs = $field->getAttributes();
        $this->assertEquals($attrs['min'], '2009-01-31T23:00:00'); // frontend timezone
        $this->assertEquals($attrs['max'], '2010-01-31T23:00:00'); // frontend timezone
    }

    public function testAttributesNonHTML5()
    {
        $field = new DatetimeField('Datetime');
        $field->setHTML5(false);
        $result = $field->getAttributes();
        $this->assertSame('text', $result['type']);
    }

    public function testFrontendToInternalEdgeCases()
    {
        $field = new DatetimeField('Datetime');

        $this->assertNull($field->frontendToInternal(false));
        $this->assertNull($field->frontendToInternal('sdfsdfsfs$%^&*'));
    }

    public function testInternalToFrontendEdgeCases()
    {
        $field = new DatetimeField('Datetime');

        $this->assertNull($field->internalToFrontend(false));
        $this->assertNull($field->internalToFrontend('sdfsdfsfs$%^&*'));
    }

    public function testPerformReadonlyTransformation()
    {
        $field = new DatetimeField('Datetime');

        $result = $field->performReadonlyTransformation();
        $this->assertInstanceOf(DatetimeField::class, $result);
        $this->assertNotSame($result, $field, 'Readonly field should be cloned');
        $this->assertTrue($result->isReadonly());
    }

    public function testSetTimezoneThrowsExceptionWhenChangingTimezoneAfterSettingValue()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Can't change timezone after setting a value");
        date_default_timezone_set('Europe/Berlin');
        $field = new DatetimeField('Datetime', 'Time', '2003-03-29 23:59:38');
        $field->setTimezone('Pacific/Auckland');
    }

    public function testModifyReturnNewField(): void
    {
        $globalStateNow = '2020-01-01 00:00:00';
        DBDatetime::set_mock_now($globalStateNow);

        // Suppose we need to know the current time in our feature, we store it in a variable
        // Make this field immutable, so future modifications don't apply to any other object references
        $now = DBDatetime::now()->setImmutable(true);

        // Later in the code we want to know the time value for 10 days later, we can reuse our $now variable
        $later = $now->modify('+ 10 days')->Rfc2822();

        // Our expectation is that this code should not apply the change to our
        // $now variable declared earlier in the code
        $this->assertSame('2020-01-11 00:00:00', $later, 'We expect to get a future datetime');
        $this->assertSame($globalStateNow, $now->Rfc2822(), 'We expect to get the current datetime');
    }

    protected function getMockForm()
    {
        /** @skipUpgrade */
        return new Form(
            Controller::curr(),
            'Form',
            new FieldList(),
            new FieldList(
                new FormAction('doSubmit')
            )
        );
    }
}
