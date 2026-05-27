<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Artikel &amp; Berita AI — {{ config('app.name', 'Laravel Starter') }}</title>
    
    {{-- Tailwind CSS CDN for instant bulletproof rendering --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Instrument Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        /* Smooth hover cards */
        .article-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .article-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px -8px rgba(37, 99, 235, 0.1), 0 4px 12px -4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased flex flex-col min-h-screen">

    {{-- NAVBAR --}}
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 group">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 shadow-sm transition-shadow group-hover:shadow-md">
                        <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold tracking-tight text-gray-900">Laravel Starter</span>
                </a>
                <div class="flex items-center gap-4">
                    <a href="/" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-blue-50 hover:text-blue-600">Beranda</a>
                    <a href="/admin" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 hover:shadow-md">
                        Admin Panel
                    </a>
                </div>
            </div>
        </div>
    </nav>

    {{-- HEADER --}}
    <header class="pt-32 pb-12 bg-white border-b border-gray-100">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="max-w-2xl">
                <span class="inline-block rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-600 mb-4">Blog &amp; Berita</span>
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">Membaca Wawasan AI Terkini</h1>
                <p class="mt-4 text-lg text-gray-500">Temukan artikel berkualitas tentang AI Agent, Machine Learning, dan perkembangan teknologi mutakhir di platform kami.</p>
            </div>
        </div>
    </header>

    {{-- MAIN SECTION --}}
    <main class="mx-auto max-w-6xl px-6 py-12 lg:px-8 flex-grow w-full">
        @if($articles->count() > 0)
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($articles as $article)
                    <article class="article-card flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white">
                        {{-- Featured Image --}}
                        <div class="aspect-[16/9] bg-blue-50 relative overflow-hidden flex items-center justify-center">
                            @if($article->featured_image)
                                <img src="{{ \Illuminate\Support\Str::startsWith($article->featured_image, ['http://', 'https://']) ? $article->featured_image : Storage::url($article->featured_image) }}" alt="{{ $article->title }}" class="object-cover w-full h-full" />
                            @else
                                {{-- Placeholder premium gradient & icon --}}
                                <div class="absolute inset-0 bg-gradient-to-tr from-blue-600 to-indigo-500 opacity-90"></div>
                                <svg class="h-10 w-10 text-white/80 relative z-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V6.125c0-.621.504-1.125 1.125-1.125H9.75M8.25 21h8.25" />
                                </svg>
                            @endif
                        </div>

                        {{-- Card content --}}
                        <div class="flex flex-1 flex-col justify-between p-6">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-3">
                                    @if($article->category)
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                            {{ $article->category->name }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400">
                                        {{ $article->reading_time }} min read
                                    </span>
                                </div>
                                <a href="{{ route('public.articles.show', $article->slug) }}" class="group block">
                                    <h2 class="text-xl font-bold leading-tight text-gray-900 transition-colors group-hover:text-blue-600">
                                        {{ $article->title }}
                                    </h2>
                                    <p class="mt-3 text-sm leading-relaxed text-gray-500 line-clamp-3">
                                        {{ $article->excerpt ?? Str::limit(strip_tags($article->content), 120) }}
                                    </p>
                                </a>
                            </div>

                            {{-- Footer/Author --}}
                            <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700 font-semibold text-sm">
                                    {{ substr($article->author->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-900">{{ $article->author->name }}</p>
                                    <p class="text-[10px] text-gray-400">
                                        {{ $article->published_at?->translatedFormat('d M Y') ?? $article->created_at->translatedFormat('d M Y') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Pagination Links --}}
            <div class="mt-12 border-t border-gray-100 pt-6">
                {{ $articles->links() }}
            </div>
        @else
            <div class="text-center py-20 bg-white rounded-2xl border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18V6.125c0-.621.504-1.125 1.125-1.125H9.75M8.25 21h8.25" />
                </svg>
                <h3 class="mt-4 text-lg font-semibold text-gray-900">Belum ada artikel</h3>
                <p class="mt-2 text-sm text-gray-500">Artikel sedang dalam proses penulisan. Silahkan kembali lagi nanti.</p>
            </div>
        @endif
    </main>

    {{-- FOOTER --}}
    <footer class="border-t border-gray-100 bg-white mt-auto">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row lg:px-8">
            <p class="text-sm text-gray-400">
                Laravel Starter &mdash; v{{ app()->version() }}
            </p>
            <div class="flex items-center gap-6">
                <a href="/" class="text-sm text-gray-500 transition-colors hover:text-blue-600">Beranda</a>
                <a href="/admin" class="text-sm text-gray-500 transition-colors hover:text-blue-600">Admin Panel</a>
            </div>
        </div>
    </footer>

</body>
</html>
