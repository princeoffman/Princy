<?php

use PHPUnit\Framework\TestCase;

class PresentersTest extends TestCase
{
    public function testBuildProjectsQueryDefaultsToNewestFirst()
    {
        $sql = build_projects_query('default');

        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringEndsWith('ORDER BY p.created_at DESC', $sql);
    }

    public function testNewSortMatchesDefaultSort()
    {
        $this->assertSame(build_projects_query('default'), build_projects_query('new'));
    }

    public function testTrendingSortRandomisesResults()
    {
        $this->assertStringEndsWith('ORDER BY RAND(), p.created_at DESC', build_projects_query('trending'));
    }

    public function testSearchTermIsAppliedToTitleDescriptionAndAuthor()
    {
        $sql = build_projects_query('new', 'robot');

        $this->assertStringContainsString("p.title LIKE '%robot%'", $sql);
        $this->assertStringContainsString("p.description LIKE '%robot%'", $sql);
        $this->assertStringContainsString("u.full_name LIKE '%robot%'", $sql);
        $this->assertLessThan(strpos($sql, 'ORDER BY'), strpos($sql, 'WHERE'));
    }

    public function testSearchIsCombinedWithTrendingSort()
    {
        $sql = build_projects_query('trending', 'robot');

        $this->assertStringContainsString("WHERE (p.title LIKE '%robot%'", $sql);
        $this->assertStringEndsWith('ORDER BY RAND(), p.created_at DESC', $sql);
    }

    public function testFormatMessageRowMarksOwnMessagesAsSent()
    {
        $row = $this->messageRow();

        $formatted = format_message_row($row, 1);

        $this->assertSame('sent', $formatted['type']);
        $this->assertSame([
            'id',
            'sender_id',
            'receiver_id',
            'message',
            'sent_at',
            'sender_name',
            'sender_avatar',
            'type',
        ], array_keys($formatted));
    }

    public function testFormatMessageRowMarksOtherMessagesAsReceived()
    {
        $formatted = format_message_row($this->messageRow(), 2);

        $this->assertSame('received', $formatted['type']);
        $this->assertSame('Hello', $formatted['message']);
        $this->assertSame('Prince Takunda', $formatted['sender_name']);
    }

    public function testFormatMessageRowComparesIdsLoosely()
    {
        $row = $this->messageRow(['sender_id' => '1']);

        $this->assertSame('sent', format_message_row($row, 1)['type']);
    }

    public function testFormatUserRowMapsOnlineStatus()
    {
        $formatted = format_user_row([
            'id' => 9,
            'username' => 'prince',
            'full_name' => 'Prince Takunda',
            'avatar' => 'uploads/user_9.jpg',
            'online_status' => 1,
        ]);

        $this->assertSame([
            'id' => 9,
            'username' => 'prince',
            'name' => 'Prince Takunda',
            'avatar' => 'uploads/user_9.jpg',
            'status' => 'online',
        ], $formatted);
    }

    /**
     * @dataProvider offlineStatuses
     */
    public function testFormatUserRowMapsOfflineStatus($status)
    {
        $formatted = format_user_row([
            'id' => 9,
            'username' => 'prince',
            'full_name' => 'Prince Takunda',
            'avatar' => null,
            'online_status' => $status,
        ]);

        $this->assertSame('offline', $formatted['status']);
        $this->assertNull($formatted['avatar']);
    }

    public function offlineStatuses()
    {
        return [
            'integer zero' => [0],
            'string zero' => ['0'],
            'null' => [null],
        ];
    }

    private function messageRow(array $overrides = [])
    {
        return array_merge([
            'id' => 4,
            'sender_id' => 1,
            'receiver_id' => 2,
            'message' => 'Hello',
            'sent_at' => '2025-01-01 10:00:00',
            'sender_name' => 'Prince Takunda',
            'sender_avatar' => 'uploads/user_1.jpg',
        ], $overrides);
    }
}
