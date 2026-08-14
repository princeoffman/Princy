<?php
/**
 * Query building and row formatting helpers used by the read endpoints.
 */

/**
 * Build the projects listing query.
 *
 * @param string $sort           one of 'default', 'new', 'trending'
 * @param string $escaped_search search term already escaped for the connection
 */
function build_projects_query($sort, $escaped_search = '')
{
    $sql = "SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM projects p
        JOIN users u ON p.user_id = u.id";

    if ($escaped_search !== '') {
        $sql .= " WHERE (p.title LIKE '%$escaped_search%'"
            . " OR p.description LIKE '%$escaped_search%'"
            . " OR u.full_name LIKE '%$escaped_search%')";
    }

    $sql .= $sort === 'trending'
        ? " ORDER BY RAND(), p.created_at DESC"
        : " ORDER BY p.created_at DESC";

    return $sql;
}

/**
 * Shape a joined messages row for the API response.
 */
function format_message_row(array $row, $sender_id)
{
    return [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'receiver_id' => $row['receiver_id'],
        'message' => $row['message'],
        'sent_at' => $row['sent_at'],
        'sender_name' => $row['sender_name'],
        'sender_avatar' => $row['sender_avatar'],
        'type' => ($row['sender_id'] == $sender_id) ? 'sent' : 'received',
    ];
}

/**
 * Shape a users row for the API response.
 */
function format_user_row(array $row)
{
    return [
        'id' => $row['id'],
        'username' => $row['username'],
        'name' => $row['full_name'],
        'avatar' => $row['avatar'],
        'status' => $row['online_status'] ? 'online' : 'offline',
    ];
}
