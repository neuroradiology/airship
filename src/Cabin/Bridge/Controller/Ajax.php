<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Alerts\CabinNotFound;
use Airship\Cabin\Bridge\Model\{
    Announcements,
    Author,
    Blog,
    Files,
    Permissions,
    UserAccounts
};

require_once __DIR__.'/init_gear.php';

/**
 * Class Ajax
 * @package Airship\Cabin\Bridge\Controller
 */
class Ajax extends LoggedInUsersOnly
{
    /**
     * @route announcement/dismiss
     * @throws \TypeError
     */
    public function dismissAnnouncement()
    {
        $announce_bp = $this->model('Announcements');
        if (!($announce_bp instanceof Announcements)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Announcements::class)
            );
        }
        if (empty($_POST['dismiss'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $result = $announce_bp->dismissForUser(
            $this->getActiveUserId(),
            $_POST['dismiss']
        );
        if ($result) {
            \Airship\json_response([
                'status' => 'OK',
                'message' => ''
            ]);
        }
        \Airship\json_response([
            'status' => 'ERROR',
            'message' => 'An unknown error has occurred.'
        ]);
    }

    /**
     * @route ajax/authors_get_photo
     * @throws \TypeError
     */
    public function getAuthorsPhoto()
    {
        $auth_bp = $this->model('Author');
        if (!($auth_bp instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $file_bp = $this->model('Files');
        if (!($file_bp instanceof Files)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Files::class)
            );
        }
        $authorId = (int) ($_POST['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        if (!\Airship\all_keys_exist(['context', 'author'], $_POST)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $file = $auth_bp->getPhotoData($authorId, $_POST['context']);
        if (empty($file)) {
            // No file selected
            \Airship\json_response([
                'status' => 'OK',
                'message' => '',
                'photo' => null
            ]);
        }
        $cabin = $file_bp->getFilesCabin((int) $file['fileid']);
        \Airship\json_response([
            'status' => 'OK',
            'message' => '',
            'photo' =>
                \Airship\ViewFunctions\cabin_url($cabin) .
                    'files/author/' .
                    $file['slug'] . '/' .
                    $auth_bp->getPhotoDirName() . '/' .
                    $file['filename']
        ]);
    }

    /**
     * @route ajax/authors_photo_available
     * @throws \TypeError
     */
    public function getAuthorsAvailablePhotos()
    {
        $auth_bp = $this->model('Author');
        if (!($auth_bp instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }

        $authorId = (int) ($_POST['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }

        if (empty($_POST['cabin']) || !$authorId === 0) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }

        \Airship\json_response([
            'status' => 'OK',
            'message' => '',
            'photos' => $auth_bp->getAvailablePhotos($authorId, $_POST['cabin'])
        ]);
    }

    /**
     * @route ajax/authors_blog_posts
     * @throws \TypeError
     */
    public function getBlogPostsForAuthor()
    {
        $auth_bp = $this->model('Author');
        if (!($auth_bp instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $blog_bp = $this->model('Blog');
        if (!($blog_bp instanceof Blog)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Blog::class)
            );
        }

        if (empty($_POST['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = (int) ($_POST['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $_POST['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = (int) $e;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($_POST['add'])) {
            $newBlogPost = $blog_bp->getBlogPostById($_POST['add'] + 0);
            if (!empty($newBlogPost)) {
                if ($newBlogPost['author'] === $authorId) {
                    $existing[] = (int) ($_POST['add'] ?? 0);
                    $response['new_item'] = $this->getViewAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newBlogPost['title'],
                                'post' => $newBlogPost['postid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $series = $blog_bp->listPostsForAuthor($authorId, $existing);
        $response['options'] = $this->getViewAsText(
            'ajax/bridge_blog_series_select_blogpost',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);
    }

    /**
     * @route ajax/get_perms_user
     */
    public function getPermsForUser()
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You are not an administrator.')
                ]
            );
        }
        if (empty($_POST['username'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You must enter a username.')
                ]
            );
        }
        if (empty($_POST['context'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('No context provided.')
                ]
            );
        }
        if (empty($_POST['cabin'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('No cabin provided.')
                ]
            );
        }
        $this->getPermissionsDataForUser(
            (int) $_POST['context'],
            $_POST['username'],
            $_POST['cabin']
        );
    }

    /**
     * @route ajax/authors_blog_series
     * @throws \TypeError
     */
    public function getSeriesForAuthor()
    {
        $auth_bp = $this->model('Author');
        if (!($auth_bp instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $blog_bp = $this->model('Blog');
        if (!($blog_bp instanceof Blog)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Blog::class)
            );
        }

        if (empty($_POST['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = (int) ($_POST['author'] ?? 0);
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $_POST['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = (int) $e;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($_POST['add'])) {
            $add = (int) ($_POST['add'] ?? 0);
            $newSeries = $blog_bp->getSeries($add);
            if (!empty($newSeries)) {
                if ($newSeries['author'] === $authorId) {
                    $existing[] = $add;
                    $response['new_item'] = $this->getViewAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newSeries['name'],
                                'series' => $newSeries['seriesid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $existing = $blog_bp->getAllSeriesParents($existing);

        $series = $blog_bp->getSeriesForAuthor($authorId, $existing);
        $response['options'] = $this->getViewAsText(
            'ajax/bridge_blog_series_select_series',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);
    }

    /**
     * @route ajax/permission_test
     */
    public function permissionTest()
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You are not an administrator.')
                ]
            );
        }
        if (empty($_POST['url'])) {
            \Airship\json_response(
                [
                    'status' => 'ERROR',
                    'message' => \__('You must enter a URL.')
                ]
            );
        }
        try {
            $cabin = $this->getCabinNameFromURL($_POST['url']);
            $this->getPermissionDataForURL($_POST['url'], $cabin);
        } catch (CabinNotFound $ex) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('URL does not resolve to an existing Cabin.')
            ]);
        }
    }

    /**
     * @route ajax/rich_text_preview
     */
    public function richTextPreview()
    {
        if (\Airship\all_keys_exist(['format', 'body'], $_POST)) {
            switch ($_POST['format']) {
                case 'HTML':
                case 'Rich Text':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\get_purified($_POST['body'] ?? '')
                    ]);
                    break;
                case 'Markdown':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\render_purified_markdown(
                            $_POST['body'] ?? '',
                            true
                        )
                    ]);
                    break;
                case 'RST':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\ViewFunctions\get_purified(
                            \Airship\ViewFunctions\render_rst($_POST['body'] ?? '', true)
                        )
                    ]);
                    break;
                default:
                    \Airship\json_response([
                        'status' => 'ERROR',
                        'message' => 'Unknown format: ' . $_POST['format']
                    ]);
            }
        }
        \Airship\json_response([
            'status' => 'ERROR',
            'message' => \__('Incomplete request')
        ]);
    }

    /**
     * @param string $url
     * @param string $cabin
     * @throws \TypeError
     */
    protected function getPermissionDataForURL(string $url, string $cabin)
    {
        $perm_bp = $this->model('Permissions');
        if (!($perm_bp instanceof Permissions)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Permissions::class)
            );
        }

        $actions = $perm_bp->getActionNames($cabin);
        $contexts = $perm_bp->getContextsForURI($url, $cabin);
        $contextIds = $perm_bp->getContextIds($url, $cabin);
        $tree = $perm_bp->buildMultiContextGroupTree(
            $cabin,
            $contextIds,
            $actions
        );
        $list = $perm_bp->buildMultiContextUserList(
            $cabin,
            $contextIds,
            $actions
        );

        $this->view(
            'perms/test',
            [
                'cabin' =>
                    $cabin,
                'actions' =>
                    $actions,
                'contexts' =>
                    $contexts,
                'permissions' =>
                    $tree,
                'userlist' =>
                    $list
            ]
        );
    }

    /**
     * @param int $contextId
     * @param string $username
     * @param string $cabin
     * @throws \TypeError
     */
    protected function getPermissionsDataForUser(int $contextId, string $username, string $cabin)
    {
        $perm_bp = $this->model('Permissions');
        if (!($perm_bp instanceof Permissions)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Permissions::class)
            );
        }
        $user_bp = $this->model('UserAccounts');
        if (!($user_bp instanceof UserAccounts)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', UserAccounts::class)
            );
        }

        $user = $user_bp->getUserByUsername($username, true);
        if (empty($user)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('There is no user with that username in the system')
            ]);
        }
        $userPerms = $perm_bp->getUserPerms($user['userid'], $contextId);
        $actions = $perm_bp->getActionNames($cabin);
        $perms = [];
        foreach ($actions as $action) {
            $perms[$action] = \in_array($action, $userPerms);
        }

        \Airship\json_response([
            'status' => 'OK',
            'message' => $this->viewRender(
                'perms/user',
                [
                    'user' =>  $user,
                    'actions' => $actions,
                    'perms' => $perms
                ]
            ),
        ]);
    }

    /**
     * @route ajax/authors_save_photo
     */
    public function saveAuthorsPhoto()
    {
        $auth_bp = $this->model('Author');
        if (!($auth_bp instanceof Author)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Author::class)
            );
        }
        $authorId = (int) $_POST['author'];
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        if (!\Airship\all_keys_exist(['cabin', 'context', 'author', 'filename'], $_POST)) {
            \Airship\json_response([
                'keys' => array_keys($_POST),
                'status' => 'ERROR',
                'message' => 'Insufficient parameters'
            ]);
        }
        $result = $auth_bp->savePhotoChoice(
            $authorId,
            $_POST['context'],
            $_POST['cabin'],
            $_POST['filename']
        );
        if (!$result) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => 'Could not save photo choice.',
                'photo' => null
            ]);
        }
        \Airship\json_response([
            'status' => 'OK',
            'message' => 'Saved!',
        ]);
    }
}
