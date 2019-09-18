<?php
/*
|------------------------------------------------------------------------------
| This is incarnation of a TodoAPP in vanilla PHP which works nicely with JS
| enabled. When JS is disabled, not that nice, yet still fully functional.
|
| It is written in this single file in ~250 loc (both PHP & HTML), implements
| crazy-simple routing, repository, POPO & pure JavaScript UX enhancements.
| It also speaks HTTP as you'd expect, so you GET and POST accordingly.
|
| The goal here is to encourage exploring basics and standards
| after one is familiar with the heavy-weight tools like the
| modern JS libraries or fully-fledged backend frameworks.
|
| Inspired by the JS TodoMVC playground @link http://todomvc.com
|------------------------------------------------------------------------------
*/

/**
 * Simple POPO to hold single task's data
 */
class Todo
{
    /** @var string */
    public $body = '';
    /** @var bool */
    public $done = false;
}

/**
 * Static repository that handles data fetching & updates & talks to the storage (JSON file)
 */
class TodosRepo
{
    private static $todos_path = __DIR__.'/../todos.json';

    public static function getAll() : array
    {
        $todos = json_decode(file_get_contents(self::$todos_path), true) ?: [];

        // transform raw arrays to the Todo objects
        return array_map(function ($row) {
            $todo = new self;
            $todo->body = $row['body'] ?? '';
            $todo->done = $row['done'] ?? false;
            return $todo;
        }, $todos);
    }
    public static function getActive() : array
    {
        return array_filter(self::getAll(), function ($todo) {
            return !$todo->done;
        });
    }
    public static function getDone() : array
    {
        return array_filter(self::getAll(), function ($todo) {
            return $todo->done;
        });
    }
    public static function find(int $index) : ?self
    {
        return self::getAll()[$index] ?? null;
    }
    public static function save(?self $todo, int $index = null) : void
    {
        $todos = self::getAll();
        if ($index === null) {
            // add new entry
            $todos[] = $todo;
        } else {
            // update entry
            $todos[$index] = $todo;
        }
        // clear deleted entries
        $todos = array_values(array_filter($todos));
        file_put_contents(self::$todos_path, json_encode($todos, JSON_PRETTY_PRINT));
    }
    public static function clearDone() : void
    {
        $todos = array_values(self::getActive());
        file_put_contents(self::$todos_path, json_encode($todos, JSON_PRETTY_PRINT));
    }
}

function redirect()
{
    header('Location: '.url()); die;
}
function url(array $params = [])
{
    $base = $_SERVER['SCRIPT_NAME'];
    if (isset($_GET['show'])) {
        $params += ['show' => $_GET['show']];
    }
    return trim($base . '?' . http_build_query($params), '?');
}

// Routing handled via 'action' GET param
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? null;

    // ========================================================================
    // route: clear DONE todos:
    if ($action === 'clear_done') {
        TodosRepo::clearDone();
    }

    // ========================================================================
    // route: add new TODO:
    elseif ($action === 'add'
        // validation
        && isset($_POST['body']) && trim($_POST['body'])
    ) {
        $new_todo = new Todo;
        $new_todo->body = trim(strip_tags($_POST['body']));
        TodosRepo::save($new_todo);
    }

    // ========================================================================
    // route: update & remove todos:
    elseif ($action === 'update'
        // validation
        && is_array($_POST['todos'] ?? null)
    ) {
        foreach ($_POST['todos'] as $input) {
            $todo = TodosRepo::find($input['index'] ?? -1);
            if ($todo === null) {
                continue;
            }

            if (isset($input['delete'])) {
                TodosRepo::save(null, $input['index']);
                continue;
            }

            $todo->done = isset($input['done']) ? true : false;
            TodosRepo::save($todo, $input['index']);
        }
    }

    // ========================================================================
    // after POST request let's redirect back to the list
    redirect();
}

// Handle data filtering, then build the page
$filter = $_GET['show'] ?? null;
if ($filter === 'active') {
    $todos = TodosRepo::getActive();
} elseif ($filter === 'done') {
    $todos = TodosRepo::getDone();
} else {
    $todos = TodosRepo::getAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>VanillaPHP • TodoNotMVC</title>
</head>
<body
    <?php /* remove SUBMIT buttons which are necessary only when JS is disabled */ ?>
    onload="document.querySelectorAll('.js-hide').forEach(function (el) { el.remove() })"
>
    <div style="min-width: 640px; width: 640px; margin: auto;">
        <h1>TODOs in PHP</h1>
        <form method="post" action="<?= url(['action' => 'add']) ?>">
            <input type="text" size="50" name="body" placeholder="oh oh what to do?" autofocus>
            <input type="submit" value="ADD">
        </form>

        <hr>

        <ul style="list-style: none">
            <form method="post" action="<?= url(['action' => 'update']) ?>">
                <?php foreach ($todos as $index => $todo) : ?>
                    <li>
                        <label>
                            <input type="hidden" name="todos[<?= $index ?>][index]" value="<?= $index ?>">
                            <input type="checkbox"
                                name="todos[<?= $index ?>][done]"
                                onchange="this.form.submit()"
                                <?= $todo->done ? 'checked' : '' ?>
                            >
                            <span style="<?= $todo->done ? 'text-decoration: line-through;' : '' ?>">
                                <?= htmlspecialchars($todo->body) ?>
                            </span>
                        </label>
                        <label style="float: right;">
                            <small style="color: red;">delete</small>
                            <input type="checkbox"
                                name="todos[<?= $index ?>][delete]"
                                onchange="this.form.submit()"
                            >
                        </label>
                    </li>
                <?php endforeach; ?>
                <input type="submit" value="Update" class="js-hide">
            </form>
        </ul>

        <hr>

        <p>
            <div style="float: left; margin-right: 30px;">
                <?= count(TodosRepo::getActive()) ?> items left
            </div>

            <div style="float: left;">
                <form method="get">
                    <label style="padding: 3px; border: #aaa solid <?= ($_GET['show'] ?? 'all') === 'all' ? '1' : '0' ?>px;">
                        <input type="radio"
                            name="show"
                            value="all"
                            onchange="this.form.submit()"
                            style="visibility: hidden; display:none"
                            <?= ($_GET['show'] ?? 'all') === 'all' ? 'checked' : '' ?>
                        >
                        <small>All</small>
                    </label>
                    <label style="padding: 3px; border: #aaa solid <?= ($_GET['show'] ?? null) === 'active' ? '1' : '0' ?>px;">
                        <input type="radio"
                            name="show"
                            value="active"
                            onchange="this.form.submit()"
                            style="visibility: hidden; display:none"
                            <?= ($_GET['show'] ?? null) === 'active' ? 'checked' : '' ?>
                        >
                        <small>Active</small>
                    </label>
                    <label style="padding: 3px; border: #aaa solid <?= ($_GET['show'] ?? null) === 'done' ? '1' : '0' ?>px;">
                        <input type="radio"
                            name="show"
                            value="done"
                            onchange="this.form.submit()"
                            style="visibility: hidden; display:none"
                            <?= ($_GET['show'] ?? null) === 'done' ? 'checked' : '' ?>
                        >
                        <small>Completed</small>
                    </label>
                    <input type="submit" value="filter" class="js-hide">
                </form>
            </div>

            <div style="float: right;">
                <form method="post" action="<?= url(['action' => 'clear_done']) ?>">
                    <input type="submit" value="Clear completed">
                </form>
            </div>
        </p>
    </div>
</body>
</html>
