<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Option;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SubsiteService
{

    public function setDatabase(?string $database): self
    {
        if ($database) {
            DatabaseService::setDb($database);
        }

        return $this;
    }


    public function getActiveBlogs(array $filter = [], ?string $startDate = null, ?string $endDate = null): Collection
    {
        $rows = collect();
            $blogs = Blog::where('archived', 0)
                ->where('deleted', 0)
                ->where('public', 1)
                ->get();

        $blogs->each(function ($blog) use ($rows, $filter) {

            $data = $this->getBlogData($blog, $filter);

            if ($data) {
                $rows->push($data);
            }
        });

        return $rows;
    }

    public function getActiveBlogsForSelect(): array
    {
        return $this->getActiveBlogs()->map(function ($blog) {
            if (isset($blog['blog_id']) && isset($blog['siteurl'])) {
                return ['blogId' => $blog['blog_id'], 'siteurl' => $blog['siteurl']];
            }

            return null;
        })->filter()->toArray();
    }

    public function getBlogsById(array $blogIds, array $filter = [])
    {
        $rows = collect();
        $blogs = Blog::whereIn('blog_id', $blogIds)->get();

        $blogs->each(function ($blog) use ($rows, $filter) {

            $data = $this->getBlogData($blog, $filter);

            if ($data) {
                $rows->push($data);
            }
        });

        return $rows;
    }

    public function getBlogUrlById(string $database, int $blogId): string
    {
        DatabaseService::setDb($database);
        $blogs = $this->getBlogsById([$blogId]);
        $url = $blogs->first()['siteurl'];
        DatabaseService::setDb(env('DB_DATABASE'));

        return $url;
    }

    public function swapUrl(string $originalUrl, string $fromDatabase, string $toDatabase): string
    {
        $databases = DatabaseService::getInverseDatabaseList();

        return str_replace($databases[$fromDatabase], $databases[$toDatabase], $originalUrl);
    }

    protected function getBlogData(Blog $blog, array $filter): ?array
    {
        if ($blog->blog_id < 2) {
            return null;
        }

        $data = [];

        $options = (new Option())->setTable('wp_' . $blog->blog_id . '_options')
            ->whereIn('option_name', ['siteurl', 'admin_email', 'current_theme', 'template', 'active_plugins'])
            ->orderBy('option_name');

        $data['blog_id'] = $blog->blog_id;
        $data['last_updated'] = $blog->last_updated;

        $options->each(function (Option $option) use (&$data) {
            $data[$option->option_name] = $option->option_value;
        });

        if ($filter && str_replace($filter, '', $data['siteurl']) === $data['siteurl']) {
            return null;
        }

        return $data;
    }
}
