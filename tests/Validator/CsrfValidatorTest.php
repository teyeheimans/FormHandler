<?php

namespace FormHandler\Tests\Validator;

use FormHandler\Form;
use FormHandler\Validator\CsrfValidator;
use FormHandler\Validator\StringValidator;

class CsrfValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test if our default csrf setting is stored correctly
     */
    public function testDefaultCsrfLogic()
    {
        // make sure the default CSRF logic works like expected
        $this->assertTrue(Form::isDefaultCsrfProtectionEnabled());

        Form::setDefaultCsrfProtectionEnabled(false);
        $this->assertFalse(Form::isDefaultCsrfProtectionEnabled());

        $form = new Form();
        $this->assertFalse($form->isCsrfProtectionEnabled());

        $form = new Form('', true);
        $this->assertTrue($form->isCsrfProtectionEnabled());

        Form::setDefaultCsrfProtectionEnabled(true);
        $this->assertTrue(Form::isDefaultCsrfProtectionEnabled());

        $form = new Form('', false);
        $this->assertFalse($form->isCsrfProtectionEnabled());
        $this->assertNull($form->getFieldByName('csrftoken')); // should not exists

        $form = new Form();
        $this->assertTrue($form->isCsrfProtectionEnabled());
        $this->assertInstanceOf('\FormHandler\Field\HiddenField', $form->getFieldByName('csrftoken'));
    }

    /**
     * When sessions are not available, csrf should not work and always be valid
     */
    public function testSessionDisaled()
    {
        $GLOBALS['mock_session_id_response'] = '';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'John'];

        $form = new Form('', true );
        $form -> textField('name') -> addValidator( new StringValidator( 2, 0, true ));

        $this -> assertFalse(
            $form -> isCsrfProtectionEnabled(),
            'csrf should be disabled because there is no session available'
        );

        $submitted = $form -> isSubmitted( $reason );
        $this -> assertTrue(
            $submitted,
            'The form should be submitted. CSRF is disabled because sessions are not available. The form is '.
            'not submitted because of reason: '. $reason
        );

        $this -> assertTrue(
            $form -> isValid(),
            'The form should be valid because the posted value is valid. CSRF should be not available because ' .
            'there are no sessions available.'
        );

        unset( $GLOBALS['mock_session_id_response'] );
    }

    /**
     * Test CSRF protection
     */
    public function testCsrf()
    {
        // first, create a Form which is "not" submitted.
        $form = new Form('', true);
        $form->textField('name');
        $this->assertTrue($form->isCsrfProtectionEnabled());

        $form->setCsrfProtection(false);
        $this->assertFalse($form->isCsrfProtectionEnabled());

        $form->setCsrfProtection(true);
        $this->assertTrue($form->isCsrfProtectionEnabled());

        // this should exists
        $field = $form->getFieldByName('csrftoken');
        $this->assertInstanceOf('\FormHandler\Field\HiddenField', $field, 'csrf field should exists');

        // this should contain a token
        $this->assertNotEmpty(
            $field->getValue(),
            'csrftoken field should contain a value, as a token should be generated'
        );

        $this->assertFalse($form->isSubmitted(), 'the field should not be submitted');
    }

    public function testCsrfWithoutTokenPosted()
    {
        // Now fake a "wrong" submit
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'John'
        ];

        // create a simular form.
        $form = new Form('', false);
        $this->assertTrue($form->isSubmitted(), 'Form should be submitted');

        $form->clearCache(); // clear static cache
        $form->textField('name');

        $this->assertTrue($form->isSubmitted(), 'Form should be submitted, name field exists');

        // enable csrf protection
        $form->setCsrfProtection(true);
        $this->assertTrue($form->isCsrfProtectionEnabled(), 'csrf protection should be enabled');
        $form->clearCache(); // clear static cache

        $this->assertFalse($form->isSubmitted(), 'Form should be not submitted, csrf token not in POST field');

        // after checking it should exists, but if should be invalid.
        $field = $form->getFieldByName('csrftoken');
        $this->assertInstanceOf('\FormHandler\Field\HiddenField', $field);

        $this->assertEmpty($field->getValue(), 'csrf token should be emty');
    }

    /**
     * Test form with invalid token
     */
    public function testCsrfWithWrongTokenPosted()
    {
        // Now fake a "wrong" submit
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'John',
            'csrftoken' => 'wrong.value'
        ];

        // create a simular form.
        $form = new Form('', false);
        $this->assertTrue($form->isSubmitted(), 'Form should be submitted');

        $form->clearCache(); // clear static cache
        $form->textField('name');

        $this->assertTrue($form->isSubmitted(), 'Form should be submitted, name field exists');

        // enable csrf protection
        $form->setCsrfProtection(true);
        $this->assertTrue($form->isCsrfProtectionEnabled(), 'csrf protection should be enabled');

        $form->clearCache(); // clear static cache

        $this->assertTrue($form->isSubmitted($reason), 'Form should be submitted, csrf token is in POST field');

        $this->assertFalse($form->isValid(), 'Form should be invalid, csrf token is not correct');

        // after checking it should exists, but if should be invalid.
        $field = $form->getFieldByName('csrftoken');
        $this->assertInstanceOf('\FormHandler\Field\HiddenField', $field);

        $this->assertEquals('wrong.value', $field->getValue(), 'csrf token should be wrong.value');
    }

    /**
     * Test token session cleanup
     */
    public function testTokenCleanup()
    {
        $_SESSION['csrftokens'] = ''; // test incorrect type;

        new CsrfValidator();
        $this->assertTrue(
            is_array($_SESSION['csrftokens']),
            'Session csrftokens should now be an array'
        );

        // add some wrong tokens. They should be removed afterwards
        $_SESSION['csrftokens'][] = 'wrong.token';
        $expired = (time() - 86400) . '.invalid';
        $_SESSION['csrftokens'][] = $expired;

        new CsrfValidator();

        $this->assertNotContains(
            'wrong.token',
            $_SESSION['csrftokens'],
            'CSRF session should not contain "wrong.token" anymore as its not a timestamp'
        );
        $this->assertNotContains(
            $expired,
            $_SESSION['csrftokens'],
            'CSRF token should be removed because its timestamp is expired'
        );

        $expired = (time() - 86400) . '.expired';
        $notExpired = (time() - 6200) . '.not-expired';

        $_SESSION['csrftokens'] = [$expired, $notExpired];
        define('CSRFTOKEN_EXPIRE', 6600);

        new CsrfValidator();

        $this->assertNotContains(
            $expired,
            $_SESSION['csrftokens'],
            'CSRF session should not contain the expired token'
        );

        $this->assertContains(
            $notExpired,
            $_SESSION['csrftokens'],
            'CSRF session should contain ' . $notExpired . ' because its not expired yet'
        );
    }

    /**
     * Test valid CSRF token
     */
    public function testValidCsrfFlow()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // form should not be submitted.
        $form = new Form(null, true);
        $form->textField('name');

        $this->assertFalse($form->isSubmitted(), 'Form should not be submitted');

        $token = $form('csrftoken')->getValue();

        $this->assertTrue(is_array($_SESSION['csrftokens']));

        // now fake a post and retry
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'Piet',
            'csrftoken' => $token
        ];

        // form should be submitted.
        $form = new Form(null, true);
        $form->textField('name');

        $this->assertTrue($form->isSubmitted(), 'Form should be submitted');

        $valid = $form->isValid();
        $this->assertTrue($valid, 'Form should be valid, token is in the POST');
        $this->assertTrue($form->isCsrfValid());
    }

    public function testCsrfDisabled()
    {
        // now fake a post and retry
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'Piet'
        ];

        $form = new Form('', false);
        $form->textField('name');

        $this->assertTrue($form->isCsrfValid(), 'CSRF should be valid as it is disabled');
    }

    public function testCsrfValidOnNonSubmittedForm()
    {
        // now fake a post and retry
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $form = new Form('', true);
        $form->textField('name');

        $this->assertFalse($form->isSubmitted(), 'Form is not submitted');
        $this->assertTrue($form->isCsrfValid(), 'CSRF should be valid as the form is not submitted');
    }

    public function testInvalidField()
    {
        // now fake a post and retry
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'Pi'
        ];

        $form = new Form('', false);
        $form->textField('name') -> addValidator(new StringValidator(3));

        $this->assertFalse($form -> isValid(), 'Form should not be valid');
        $this->assertTrue($form->isCsrfValid(), 'CSRF should be valid as the form is invalid');
    }

    /**
     * Test valid CSRF token
     */
    public function testInvalidCsrfFlow()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // form should not be submitted.
        $form = new Form(null, true);
        $form->textField('name');

        $this->assertFalse($form->isSubmitted(), 'Form should not be submitted');

        $token = $form('csrftoken')->getValue();

        $this->assertTrue(is_array($_SESSION['csrftokens']));

        // now fake a post and retry
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'name' => 'Piet',
            'csrftoken' => $token . 'wrong'
        ];

        // form should be submitted.
        $form = new Form(null, true);
        $form->textField('name');

        $this->assertTrue($form->isSubmitted(), 'Form should be submitted');

        $valid = $form->isValid();
        $this->assertFalse($valid, 'Form should be valid, token is in the POST');

        $this->assertFalse($form->isCsrfValid());
    }

    protected function setUp()
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SESSION = [];
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SESSION = [];
    }
}
