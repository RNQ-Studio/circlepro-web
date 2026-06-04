<?php

namespace Database\Seeders;

use App\Models\Quote;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        $quotes = [
            // ── Panahan (Archery) ─────────────────────────────────────────
            [
                'text' => 'In archery we have something like the way of the highest. When the archer misses the center of the target, he turns round and seeks for the cause of his failure in himself.',
                'author' => 'Confucius',
                'source' => null,
            ],
            [
                'text' => 'Aiming is not about hitting the target. Aiming is about becoming one with it.',
                'author' => 'Eugen Herrigel',
                'source' => 'Zen in the Art of Archery',
            ],
            [
                'text' => 'The archer ceases to be conscious of himself as the one who is engaged in hitting the bull\'s-eye. This state of unconscious is realized only when, completely empty and rid of the self, he becomes one with the perfecting of his technical skill.',
                'author' => 'Eugen Herrigel',
                'source' => 'Zen in the Art of Archery',
            ],
            [
                'text' => 'Don\'t think of what you have to do, don\'t consider how to carry it out! The shot will only go smoothly when it takes the archer himself by surprise.',
                'author' => 'Eugen Herrigel',
                'source' => 'Zen in the Art of Archery',
            ],
            [
                'text' => 'The more obstinately you try to learn how to shoot the arrow for the sake of hitting the goal, the less you will succeed.',
                'author' => 'Eugen Herrigel',
                'source' => 'Zen in the Art of Archery',
            ],
            [
                'text' => 'An arrow can only be shot by pulling it backward. So when life is dragging you back with difficulties, it means that it\'s going to launch you into something great.',
                'author' => 'Paulo Coelho',
                'source' => null,
            ],
            [
                'text' => 'The archer who overshoots his mark does no better than he who falls short of it.',
                'author' => 'Michel de Montaigne',
                'source' => null,
            ],
            [
                'text' => 'The way of the bow is the way of purposeful living. Those who walk this path learn to see with the heart and release with trust.',
                'author' => 'Paulo Coelho',
                'source' => 'The Archer',
            ],
            [
                'text' => 'Busur, anak panah, dan sasaran. Ketiganya tidak berarti apa-apa tanpa pemanah yang tahu mengapa ia memanah.',
                'author' => 'Paulo Coelho',
                'source' => 'The Archer',
            ],
            [
                'text' => 'You are the bow, your habits are the arrows, and your goals are the target. Pull back, trust the process, and release.',
                'author' => 'Anonim',
                'source' => null,
            ],

            // ── Latihan & Disiplin ────────────────────────────────────────
            [
                'text' => 'We are what we repeatedly do. Excellence, then, is not an act, but a habit.',
                'author' => 'Aristoteles',
                'source' => null,
            ],
            [
                'text' => 'The only way to do great work is to love what you do.',
                'author' => 'Steve Jobs',
                'source' => 'Stanford Commencement Speech, 2005',
            ],
            [
                'text' => 'Hard work beats talent when talent doesn\'t work hard.',
                'author' => 'Tim Notke',
                'source' => null,
            ],
            [
                'text' => 'It\'s not the will to win that matters — everyone has that. It\'s the will to prepare to win that matters.',
                'author' => 'Paul "Bear" Bryant',
                'source' => null,
            ],
            [
                'text' => 'Practice does not make perfect. Only perfect practice makes perfect.',
                'author' => 'Vince Lombardi',
                'source' => null,
            ],
            [
                'text' => 'The fight is won or lost far away from witnesses — behind the lines, in the gym, and out there on the road, long before I dance under those lights.',
                'author' => 'Muhammad Ali',
                'source' => null,
            ],
            [
                'text' => 'Champions do not become champions when they win the event, but in the hours, weeks, months, and years they spend preparing for it.',
                'author' => 'T. Alan Armstrong',
                'source' => null,
            ],
            [
                'text' => 'Sukses bukanlah hasil satu malam. Ia adalah konsistensi harian yang tak pernah lelah berulang.',
                'author' => 'Anonim',
                'source' => null,
            ],
            [
                'text' => 'Discipline is the bridge between goals and accomplishment.',
                'author' => 'Jim Rohn',
                'source' => null,
            ],
            [
                'text' => 'Small daily improvements over time lead to stunning results.',
                'author' => 'Robin Sharma',
                'source' => null,
            ],

            // ── Target & Fokus ────────────────────────────────────────────
            [
                'text' => 'A goal without a plan is just a wish.',
                'author' => 'Antoine de Saint-Exupéry',
                'source' => null,
            ],
            [
                'text' => 'Setting goals is the first step in turning the invisible into the visible.',
                'author' => 'Tony Robbins',
                'source' => null,
            ],
            [
                'text' => 'If you aim at nothing, you will hit it every time.',
                'author' => 'Zig Ziglar',
                'source' => null,
            ],
            [
                'text' => 'Focus on the journey, not the destination. Joy is found not in finishing an activity but in doing it.',
                'author' => 'Greg Anderson',
                'source' => null,
            ],
            [
                'text' => 'Concentrate all your thoughts upon the work at hand. The sun\'s rays do not burn until brought to a focus.',
                'author' => 'Alexander Graham Bell',
                'source' => null,
            ],
            [
                'text' => 'Where focus goes, energy flows.',
                'author' => 'Tony Robbins',
                'source' => null,
            ],
            [
                'text' => 'The successful warrior is the average man, with laser-like focus.',
                'author' => 'Bruce Lee',
                'source' => null,
            ],
            [
                'text' => 'You don\'t have to be great to start, but you have to start to be great.',
                'author' => 'Zig Ziglar',
                'source' => null,
            ],
            [
                'text' => 'Obstacles are those frightful things you see when you take your eyes off your goal.',
                'author' => 'Henry Ford',
                'source' => null,
            ],
            [
                'text' => 'Ketika kau tidak lagi melihat papan skor dan mulai menikmati prosesnya, di situlah skor terbaikmu lahir.',
                'author' => 'Anonim',
                'source' => null,
            ],

            // ── Kualitas Diri & Mental ────────────────────────────────────
            [
                'text' => 'Knowing yourself is the beginning of all wisdom.',
                'author' => 'Aristoteles',
                'source' => null,
            ],
            [
                'text' => 'It is not the mountain we conquer, but ourselves.',
                'author' => 'Edmund Hillary',
                'source' => null,
            ],
            [
                'text' => 'Strength does not come from physical capacity. It comes from an indomitable will.',
                'author' => 'Mahatma Gandhi',
                'source' => null,
            ],
            [
                'text' => 'The mind is everything. What you think you become.',
                'author' => 'Buddha',
                'source' => null,
            ],
            [
                'text' => 'Be not afraid of growing slowly, be afraid only of standing still.',
                'author' => 'Peribahasa Tiongkok',
                'source' => null,
            ],
            [
                'text' => 'You are never too old to set another goal or to dream a new dream.',
                'author' => 'C.S. Lewis',
                'source' => null,
            ],
            [
                'text' => 'He who conquers himself is the mightiest warrior.',
                'author' => 'Confucius',
                'source' => null,
            ],
            [
                'text' => 'Patience is not the ability to wait, but the ability to keep a good attitude while waiting.',
                'author' => 'Joyce Meyer',
                'source' => null,
            ],
            [
                'text' => 'Seorang pemanah sejati tidak berkompetisi dengan orang lain, melainkan dengan versi terbaik dari dirinya sendiri.',
                'author' => 'Anonim',
                'source' => null,
            ],
            [
                'text' => 'What lies behind us and what lies before us are tiny matters compared to what lies within us.',
                'author' => 'Ralph Waldo Emerson',
                'source' => null,
            ],

            // ── Mimpi & Inspirasi ─────────────────────────────────────────
            [
                'text' => 'Shoot for the moon. Even if you miss, you\'ll land among the stars.',
                'author' => 'Les Brown',
                'source' => null,
            ],
            [
                'text' => 'The future belongs to those who believe in the beauty of their dreams.',
                'author' => 'Eleanor Roosevelt',
                'source' => null,
            ],
            [
                'text' => 'All our dreams can come true, if we have the courage to pursue them.',
                'author' => 'Walt Disney',
                'source' => null,
            ],
            [
                'text' => 'Dream big, start small, act now.',
                'author' => 'Robin Sharma',
                'source' => null,
            ],
            [
                'text' => 'It always seems impossible until it\'s done.',
                'author' => 'Nelson Mandela',
                'source' => null,
            ],
            [
                'text' => 'Don\'t watch the clock; do what it does. Keep going.',
                'author' => 'Sam Levenson',
                'source' => null,
            ],
            [
                'text' => 'Success is not final, failure is not fatal: it is the courage to continue that counts.',
                'author' => 'Winston Churchill',
                'source' => null,
            ],
            [
                'text' => 'Mimpimu adalah anak panah. Tekadmu adalah busurnya. Dan setiap latihan adalah tarikan tali yang mendekatkanmu ke sasaran.',
                'author' => 'Anonim',
                'source' => null,
            ],
            [
                'text' => 'Believe you can and you\'re halfway there.',
                'author' => 'Theodore Roosevelt',
                'source' => null,
            ],
            [
                'text' => 'The only limit to our realization of tomorrow will be our doubts of today.',
                'author' => 'Franklin D. Roosevelt',
                'source' => null,
            ],

            // ── Ali bin Abi Thalib ────────────────────────────────────────
            [
                'text' => 'Kesabaran itu ada dua macam: sabar atas apa yang tidak kau sukai, dan sabar untuk tidak melakukan apa yang kau sukai padahal itu buruk bagimu.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Orang yang paling berani adalah yang mampu mengalahkan hawa nafsunya.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Jangan biarkan kegagalanmu di masa lalu menjadi penghambat harapanmu di masa depan. Setiap hari adalah awal yang baru.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Ilmu itu lebih baik daripada harta. Ilmu menjagamu, sedangkan kamu menjaga harta. Ilmu bertambah jika diamalkan, sedangkan harta berkurang jika dibelanjakan.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Nilai seseorang terletak pada apa yang ia kuasai dan ia lakukan dengan baik.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Orang yang mengenal dirinya sendiri, telah mengenal Tuhannya.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Jika kamu tidak tahan terhadap lelahnya belajar, maka kamu harus siap menanggung pahitnya kebodohan.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Bekerjalah untuk duniamu seakan-akan kau hidup selamanya, dan beramallah untuk akhiratmu seakan-akan kau mati besok.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Lakukanlah kebaikan dan lupakanlah. Karena sesungguhnya melupakan kebaikan yang telah kau lakukan itulah kebaikan yang sesungguhnya.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],
            [
                'text' => 'Musuhmu yang paling besar adalah dirimu yang berada di antara kedua lambungmu.',
                'author' => 'Sayyidina Ali bin Abi Thalib',
                'source' => null,
            ],

            // ── Abu Bakar Ash-Shiddiq ─────────────────────────────────────
            [
                'text' => 'Kebaikan yang paling sulit adalah kebaikan yang dilakukan secara diam-diam.',
                'author' => 'Sayyidina Abu Bakar Ash-Shiddiq',
                'source' => null,
            ],
            [
                'text' => 'Jangan pernah meremehkan amal sekecil apapun, karena engkau tidak tahu amal mana yang akan diterima.',
                'author' => 'Sayyidina Abu Bakar Ash-Shiddiq',
                'source' => null,
            ],

            [
                'text' => 'Tanpa ilmu, amal itu tidak berguna. Dan tanpa amal, ilmu itu tidak bermanfaat.',
                'author' => 'Sayyidina Abu Bakar Ash-Shiddiq',
                'source' => null,
            ],
            [
                'text' => 'Janganlah kamu iri kepada siapapun kecuali dalam dua hal: orang yang diberi harta lalu ia menghabiskannya di jalan kebenaran, dan orang yang diberi ilmu lalu ia mengamalkannya dan mengajarkannya.',
                'author' => 'Sayyidina Abu Bakar Ash-Shiddiq',
                'source' => null,
            ],

            // ── Umar bin Khattab ──────────────────────────────────────────
            [
                'text' => 'Aku tidak pernah menyesali diamku, tetapi aku sering menyesali bicaraku.',
                'author' => 'Sayyidina Umar bin Khattab',
                'source' => null,
            ],
            [
                'text' => 'Didiklah anak-anakmu untuk zaman yang bukan zamanmu, karena mereka diciptakan untuk zaman mereka sendiri.',
                'author' => 'Sayyidina Umar bin Khattab',
                'source' => null,
            ],

            // ── Sa'ad bin Abi Waqqash ─────────────────────────────────────
            [
                'text' => 'Ajarkan anak-anakmu memanah, berenang, dan berkuda.',
                'author' => 'Sayyidina Sa\'ad bin Abi Waqqash',
                'source' => null,
            ],

            [
                'text' => 'Bersabarlah dalam setiap perjuanganmu. Sesungguhnya kemenangan itu datang bersama kesabaran.',
                'author' => 'Sayyidina Sa\'ad bin Abi Waqqash',
                'source' => null,
            ],
            [
                'text' => 'Tiga perkara yang melatih keberanian dan ketepatan: memanah, menunggang kuda, dan berenang. Ketiganya menempa jiwa untuk menghadapi hidup.',
                'author' => 'Sayyidina Sa\'ad bin Abi Waqqash',
                'source' => null,
            ],

            // ── Imam Al-Ghazali ───────────────────────────────────────────
            [
                'text' => 'Ilmu tanpa amal adalah kegilaan, dan amal tanpa ilmu adalah kesia-siaan.',
                'author' => 'Imam Al-Ghazali',
                'source' => 'Ihya Ulumiddin',
            ],
            [
                'text' => 'Jangan pernah berhenti berusaha meskipun jalannya terasa panjang, karena yang berhenti di tengah jalan tidak akan pernah sampai.',
                'author' => 'Imam Al-Ghazali',
                'source' => null,
            ],
            [
                'text' => 'Hati itu ibarat cermin. Jika ia bersih, ia akan memantulkan cahaya kebenaran. Jika ia kotor, ia tidak akan memantulkan apa-apa.',
                'author' => 'Imam Al-Ghazali',
                'source' => 'Ihya Ulumiddin',
            ],
            [
                'text' => 'Setengah dari cara menyelesaikan masalah adalah mengetahui masalah itu sendiri.',
                'author' => 'Imam Al-Ghazali',
                'source' => null,
            ],

            // ── Imam Syafi'i ──────────────────────────────────────────────
            [
                'text' => 'Orang yang bijak tidak akan merasa cukup dengan ilmu yang dimilikinya, sebagaimana lautan tidak pernah menolak air sungai.',
                'author' => 'Imam Syafi\'i',
                'source' => null,
            ],
            [
                'text' => 'Jika kamu tidak sanggup menahan lelahnya belajar, kamu harus sanggup menahan perihnya kebodohan.',
                'author' => 'Imam Syafi\'i',
                'source' => null,
            ],
            [
                'text' => 'Semakin tinggi ilmu seseorang, semakin rendah hatinya. Seperti padi, semakin berisi semakin merunduk.',
                'author' => 'Imam Syafi\'i',
                'source' => null,
            ],

            // ── Salahuddin Al-Ayyubi ──────────────────────────────────────
            [
                'text' => 'Jika kamu ingin menghancurkan peradaban manapun, hancurkan buku-bukunya. Maka manusia-manusia bodoh akan datang dengan sendirinya.',
                'author' => 'Salahuddin Al-Ayyubi',
                'source' => null,
            ],
            [
                'text' => 'Kemenangan datang kepada mereka yang bersabar dan terus berjuang, bukan kepada mereka yang banyak mengeluh.',
                'author' => 'Salahuddin Al-Ayyubi',
                'source' => null,
            ],
            [
                'text' => 'Aku tidak pernah takut kepada pasukan yang besar jumlahnya, selama di antara mereka tidak ada persatuan hati.',
                'author' => 'Salahuddin Al-Ayyubi',
                'source' => null,
            ],

            // ── Jalaluddin Rumi ───────────────────────────────────────────
            [
                'text' => 'Kemarin aku pandai, jadi aku ingin mengubah dunia. Hari ini aku bijak, jadi aku mengubah diriku sendiri.',
                'author' => 'Jalaluddin Rumi',
                'source' => null,
            ],
            [
                'text' => 'Luka adalah tempat di mana cahaya masuk ke dalam dirimu.',
                'author' => 'Jalaluddin Rumi',
                'source' => null,
            ],
            [
                'text' => 'Bersabarlah, karena apa yang kamu cari juga sedang mencarimu.',
                'author' => 'Jalaluddin Rumi',
                'source' => null,
            ],
            [
                'text' => 'Engkau bukan setetes air di lautan. Engkau adalah seluruh lautan dalam setetes air.',
                'author' => 'Jalaluddin Rumi',
                'source' => null,
            ],

            // ── Ibn Qayyim Al-Jauziyyah ───────────────────────────────────
            [
                'text' => 'Perjalanan seribu mil dimulai dengan satu langkah, dan perubahan besar dimulai dari niat yang benar.',
                'author' => 'Ibn Qayyim Al-Jauziyyah',
                'source' => null,
            ],

            // ── Hasan Al-Bashri ───────────────────────────────────────────
            [
                'text' => 'Dunia ini adalah jembatan, maka lewatilah dan jangan kau bangun rumah di atasnya.',
                'author' => 'Hasan Al-Bashri',
                'source' => null,
            ],
        ];

        foreach ($quotes as $q) {
            Quote::firstOrCreate(
                ['text' => $q['text']],
                [
                    'author' => $q['author'],
                    'source' => $q['source'],
                    'is_active' => true,
                ]
            );
        }
    }
}
