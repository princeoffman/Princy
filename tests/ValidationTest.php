<?php

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testDecodeJsonBodyReturnsDecodedArray()
    {
        $result = decode_json_body('{"username":"prince","password":"secret"}');

        $this->assertTrue($result['ok']);
        $this->assertSame(['username' => 'prince', 'password' => 'secret'], $result['data']);
    }

    /**
     * @dataProvider invalidJsonBodies
     */
    public function testDecodeJsonBodyRejectsNonObjectPayloads($raw)
    {
        $result = decode_json_body($raw);

        $this->assertFalse($result['ok']);
        $this->assertSame('Invalid JSON', $result['message']);
    }

    public function invalidJsonBodies()
    {
        return [
            'empty body' => [''],
            'malformed' => ['{"username": '],
            'scalar' => ['"prince"'],
            'null literal' => ['null'],
        ];
    }

    public function testValidateLoginInputAcceptsCredentials()
    {
        $result = validate_login_input(['username' => 'prince', 'password' => 'pw']);

        $this->assertTrue($result['ok']);
        $this->assertSame('prince', $result['username']);
        $this->assertSame('pw', $result['password']);
    }

    /**
     * @dataProvider incompleteCredentials
     */
    public function testValidateLoginInputRejectsIncompleteCredentials($data, $expected)
    {
        $result = validate_login_input($data);

        $this->assertFalse($result['ok']);
        $this->assertSame($expected, $result['message']);
    }

    public function incompleteCredentials()
    {
        return [
            'no password' => [['username' => 'prince'], 'Missing username or password'],
            'no username' => [['password' => 'pw'], 'Missing username or password'],
            'empty strings' => [['username' => '', 'password' => ''], 'Missing username or password'],
            'not an array' => [null, 'Invalid JSON'],
        ];
    }

    public function testValidateMessageInputTrimsAndCastsIds()
    {
        $result = validate_message_input([
            'sender_id' => '1',
            'receiver_id' => '2',
            'message' => "  hello there \n",
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['sender_id']);
        $this->assertSame(2, $result['receiver_id']);
        $this->assertSame('hello there', $result['text']);
    }

    public function testValidateMessageInputKeepsMessagesThatLookFalsy()
    {
        $result = validate_message_input(['sender_id' => 1, 'receiver_id' => 2, 'message' => '0']);

        $this->assertTrue($result['ok']);
        $this->assertSame('0', $result['text']);
    }

    /**
     * @dataProvider invalidMessages
     */
    public function testValidateMessageInputRejectsInvalidPayloads($data, $expected)
    {
        $result = validate_message_input($data);

        $this->assertFalse($result['ok']);
        $this->assertSame($expected, $result['message']);
    }

    public function invalidMessages()
    {
        return [
            'missing message' => [
                ['sender_id' => 1, 'receiver_id' => 2],
                'Missing required fields',
            ],
            'missing receiver' => [
                ['sender_id' => 1, 'message' => 'hi'],
                'Missing required fields',
            ],
            'whitespace only' => [
                ['sender_id' => 1, 'receiver_id' => 2, 'message' => "   \t"],
                'Message cannot be empty',
            ],
            'zero sender' => [
                ['sender_id' => 0, 'receiver_id' => 2, 'message' => 'hi'],
                'Invalid sender or receiver ID',
            ],
            'non numeric receiver' => [
                ['sender_id' => 1, 'receiver_id' => 'abc', 'message' => 'hi'],
                'Invalid sender or receiver ID',
            ],
            'negative receiver' => [
                ['sender_id' => 1, 'receiver_id' => -3, 'message' => 'hi'],
                'Invalid sender or receiver ID',
            ],
            'self message' => [
                ['sender_id' => 7, 'receiver_id' => '7', 'message' => 'hi'],
                'Cannot send message to yourself',
            ],
            'not an array' => [null, 'Missing required fields'],
        ];
    }

    public function testValidateContactInputTrimsFieldsAndDefaultsOptionalOnesToNull()
    {
        $result = validate_contact_input([
            'user_id' => 3,
            'contact_name' => '  Kuda Mapinga ',
            'contact_email' => ' kuda@example.com ',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['user_id']);
        $this->assertSame([
            'contact_name' => 'Kuda Mapinga',
            'contact_email' => 'kuda@example.com',
            'contact_phone' => null,
            'contact_notes' => null,
        ], $result['contact']);
    }

    /**
     * @dataProvider invalidContacts
     */
    public function testValidateContactInputRejectsInvalidPayloads($data, $expected)
    {
        $result = validate_contact_input($data);

        $this->assertFalse($result['ok']);
        $this->assertSame($expected, $result['message']);
    }

    public function invalidContacts()
    {
        return [
            'missing user' => [['contact_name' => 'Kuda'], 'Missing required fields'],
            'missing name' => [['user_id' => 1], 'Missing required fields'],
            'blank name' => [
                ['user_id' => 1, 'contact_name' => '   '],
                'Contact name cannot be empty',
            ],
            'not an array' => [null, 'Missing required fields'],
        ];
    }

    public function testValidateProjectInputFallsBackToDefaultImage()
    {
        $result = validate_project_input([
            'user_id' => 5,
            'title' => ' My Project ',
            'description' => ' Something cool ',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('My Project', $result['project']['title']);
        $this->assertSame('Something cool', $result['project']['description']);
        $this->assertSame(DEFAULT_PROJECT_IMAGE, $result['project']['image']);
    }

    public function testValidateProjectInputKeepsProvidedImage()
    {
        $result = validate_project_input([
            'user_id' => 5,
            'title' => 'T',
            'description' => 'D',
            'image' => ' https://example.com/a.png ',
        ]);

        $this->assertSame('https://example.com/a.png', $result['project']['image']);
    }

    public function testValidateProjectInputUsesDefaultImageWhenBlank()
    {
        $result = validate_project_input([
            'user_id' => 5,
            'title' => 'T',
            'description' => 'D',
            'image' => '   ',
        ]);

        $this->assertSame(DEFAULT_PROJECT_IMAGE, $result['project']['image']);
    }

    /**
     * @dataProvider invalidProjects
     */
    public function testValidateProjectInputRejectsInvalidPayloads($data, $expected)
    {
        $result = validate_project_input($data);

        $this->assertFalse($result['ok']);
        $this->assertSame($expected, $result['message']);
    }

    public function invalidProjects()
    {
        return [
            'missing description' => [
                ['user_id' => 1, 'title' => 'T'],
                'Missing required fields',
            ],
            'missing user' => [
                ['title' => 'T', 'description' => 'D'],
                'Missing required fields',
            ],
            'blank title' => [
                ['user_id' => 1, 'title' => '  ', 'description' => 'D'],
                'Title and description cannot be empty',
            ],
            'blank description' => [
                ['user_id' => 1, 'title' => 'T', 'description' => ''],
                'Title and description cannot be empty',
            ],
            'not an array' => [null, 'Missing required fields'],
        ];
    }

    /**
     * @dataProvider idParams
     */
    public function testValidateIdParam($value, $expectedOk, $expectedId)
    {
        $result = validate_id_param($value);

        $this->assertSame($expectedOk, $result['ok']);

        if ($expectedOk) {
            $this->assertSame($expectedId, $result['id']);
        } else {
            $this->assertSame('Invalid user ID', $result['message']);
        }
    }

    public function idParams()
    {
        return [
            'numeric string' => ['42', true, 42],
            'integer' => [7, true, 7],
            'trailing text' => ['12abc', true, 12],
            'zero' => ['0', false, null],
            'negative' => [-1, false, null],
            'missing' => [null, false, null],
            'non numeric' => ['abc', false, null],
        ];
    }

    public function testValidateIdParamUsesCustomMessage()
    {
        $result = validate_id_param(0, 'Invalid project ID');

        $this->assertFalse($result['ok']);
        $this->assertSame('Invalid project ID', $result['message']);
    }

    public function testValidateAvatarUploadAcceptsSupportedImage()
    {
        $result = validate_avatar_upload([
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/png',
            'size' => 1024,
            'name' => 'me.png',
        ]);

        $this->assertTrue($result['ok']);
    }

    public function testValidateAvatarUploadAcceptsFileAtSizeLimit()
    {
        $result = validate_avatar_upload([
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg',
            'size' => MAX_AVATAR_SIZE,
            'name' => 'me.jpg',
        ]);

        $this->assertTrue($result['ok']);
    }

    /**
     * @dataProvider invalidUploads
     */
    public function testValidateAvatarUploadRejectsInvalidFiles($file, $expected)
    {
        $result = validate_avatar_upload($file);

        $this->assertFalse($result['ok']);
        $this->assertSame($expected, $result['message']);
    }

    public function invalidUploads()
    {
        return [
            'no file' => [null, 'No file uploaded or upload error'],
            'upload error' => [
                ['error' => UPLOAD_ERR_PARTIAL, 'type' => 'image/png', 'size' => 10],
                'No file uploaded or upload error',
            ],
            'unsupported type' => [
                ['error' => UPLOAD_ERR_OK, 'type' => 'application/pdf', 'size' => 10],
                'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.',
            ],
            'too large' => [
                ['error' => UPLOAD_ERR_OK, 'type' => 'image/gif', 'size' => MAX_AVATAR_SIZE + 1],
                'File too large. Maximum size is 5MB.',
            ],
        ];
    }

    /**
     * @dataProvider avatarNames
     */
    public function testAvatarFilename($userId, $originalName, $timestamp, $expected)
    {
        $this->assertSame($expected, avatar_filename($userId, $originalName, $timestamp));
    }

    public function avatarNames()
    {
        return [
            'simple' => [1, 'photo.jpg', 1760966271, 'user_1_1760966271.jpg'],
            'numeric string user id' => ['12', 'photo.PNG', 1000, 'user_12_1000.PNG'],
            'multiple dots' => [3, 'my.holiday.photo.webp', 5, 'user_3_5.webp'],
            'no extension' => [4, 'photo', 6, 'user_4_6'],
        ];
    }
}
