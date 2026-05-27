<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $article->title }} — {{ config('app.name', 'Laravel Starter') }}</title>
    
    {{-- Tailwind CSS CDN & Typography Plugin for premium rich text rendering --}}
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
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
</head>
<body class="bg-white text-gray-900 antialiased flex flex-col min-h-screen">

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
                    <a href="{{ route('public.articles.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-blue-50 hover:text-blue-600">Semua Artikel</a>
                    <a href="/admin" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 hover:shadow-md">
                        Admin Panel
                    </a>
                </div>
            </div>
        </div>
    </nav>

    {{-- ARTICLE CONTAINER --}}
    <main class="mx-auto max-w-3xl px-6 pt-32 pb-16 lg:px-8 flex-grow w-full">
        
        {{-- Category & Reading Time --}}
        <div class="flex items-center gap-3 mb-6">
            @if($article->category)
                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    {{ $article->category->name }}
                </span>
            @endif
            <span class="text-xs text-gray-400 font-medium">
                {{ $article->reading_time }} min read
            </span>
        </div>

        {{-- Title --}}
        <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl md:text-5xl leading-tight mb-8">
            {{ $article->title }}
        </h1>

        {{-- Author Box --}}
        <div class="flex items-center gap-4 mb-10 pb-8 border-b border-gray-100">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-700 font-bold text-lg">
                {{ substr($article->author->name, 0, 1) }}
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">{{ $article->author->name }}</p>
                <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-400">
                    <span>Diterbitkan pada</span>
                    <span class="h-1 w-1 rounded-full bg-gray-300"></span>
                    <span>{{ $article->published_at?->translatedFormat('d M Y') ?? $article->created_at->translatedFormat('d M Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Featured Image --}}
        @if($article->featured_image)
            <div class="rounded-2xl overflow-hidden mb-12 shadow-sm border border-gray-100">
                <img src="{{ \Illuminate\Support\Str::startsWith($article->featured_image, ['http://', 'https://']) ? $article->featured_image : Storage::url($article->featured_image) }}" alt="{{ $article->title }}" class="w-full object-cover" />
            </div>
        @endif

        {{-- Article Content --}}
        <article class="prose prose-blue prose-lg max-w-none text-gray-800 leading-relaxed mb-12">
            {!! $article->content !!}
        </article>

        {{-- Tags list --}}
        @if($article->tags->count() > 0)
            <div class="flex flex-wrap gap-2 pt-6 border-t border-gray-100 mb-16">
                @foreach($article->tags as $tag)
                    <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-3 py-1 text-xs font-medium text-gray-600">
                        #{{ $tag->name }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- RELATED ARTICLES SECTION --}}
        @if($relatedArticles->count() > 0)
            <section class="border-t border-gray-100 pt-12">
                <h3 class="text-xl font-extrabold text-gray-900 mb-6">Artikel Terkait</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($relatedArticles as $related)
                        <a href="{{ route('public.articles.show', $related->slug) }}" class="group block rounded-xl border border-gray-100 bg-gray-50 p-5 transition-all hover:border-blue-200 hover:bg-blue-50">
                            <span class="inline-block text-[10px] font-semibold text-blue-600 uppercase tracking-wider mb-2">{{ $related->category->name ?? 'Kategori' }}</span>
                            <h4 class="text-sm font-bold text-gray-900 transition-colors group-hover:text-blue-600 line-clamp-2 leading-tight">
                                {{ $related->title }}
                            </h4>
                            <p class="text-[11px] text-gray-400 mt-3">{{ $related->published_at?->translatedFormat('d M Y') }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </main>

    {{-- FOOTER --}}
    <footer class="border-t border-gray-100 bg-gray-50 mt-auto">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row lg:px-8">
            <p class="text-sm text-gray-400">
                Laravel Starter &mdash; v{{ app()->version() }}
            </p>
            <div class="flex items-center gap-6">
                <a href="{{ route('public.articles.index') }}" class="text-sm text-gray-500 transition-colors hover:text-blue-600">Semua Artikel</a>
                <a href="/" class="text-sm text-gray-500 transition-colors hover:text-blue-600">Beranda</a>
            </div>
        </div>
    </footer>

</body>
</html>
