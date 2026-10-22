<?php
// =========================================================================
// CLASS 1: UrlEncoder
// รับ URL ยาวมา แปลงเป็น Code 6 หลัก (ใช้ Base62)
// =========================================================================
class UrlEncoder {
    private const BASE62_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function encode(int $length = 6): string {
        $code = '';$maxIndex = strlen(self::BASE62_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= self::BASE62_CHARS[random_int(0,$maxIndex)];
        }
        return $code;
    }
}

// =========================================================================
// CLASS 2: LinkItem
// เก็บข้อมูล URL ต้นฉบับ, Short Code, และนับจำนวนคลิก (Click Counter)
// =========================================================================
class LinkItem implements JsonSerializable {
    private string $originalUrl;
    private string $shortCode;
    private int $clicks;
    private string $createdAt;

    public function __construct(string $originalUrl, string$shortCode, int $clicks = 0, ?string $createdAt = null) {
        $this->originalUrl =$originalUrl;
        $this->shortCode =$shortCode;
        $this->clicks =$clicks;
        $this->createdAt =$createdAt ?? date('c');
    }

    public function getOriginalUrl(): string { return $this->originalUrl; }
    public function getShortCode(): string { return $this->shortCode; }
    public function getClicks(): int { return $this->clicks; }
    public function getCreatedAt(): string { return $this->createdAt; }

    public function incrementClick(): void {
        $this->clicks++;
    }

    public function jsonSerialize(): array {
        return [
            'originalUrl' => $this->originalUrl,
            'shortCode'   => $this->shortCode,
            'clicks'      => $this->clicks,
            'createdAt'   => $this->createdAt,
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['originalUrl'],$data['shortCode'],
            $data['clicks'] ?? 0,$data['createdAt'] ?? null
        );
    }
}

// =========================================================================
// CLASS 3: RedirectManager
// ค้นหา Code และอัปเดตสถิติคนคลิกก่อนส่ง URL กลับไป Redirect
// =========================================================================
class RedirectManager {
    private string $storageFile;
    /** @var array<string, LinkItem> */
    private array $links = [];

    public function __construct(string $storageFile = 'links.json') {
        $this->storageFile =$storageFile;
        $this->loadFromStorage();
    }

    public function createShortLink(string $originalUrl): LinkItem {
        do {
            $code = UrlEncoder::encode(6);
        } while (isset($this->links[$code]));

        $linkItem = new LinkItem($originalUrl, $code);$this->links[$code] =$linkItem;
        $this->saveToStorage();

        return $linkItem;
    }

    public function handleRedirect(string $code): ?string {
        if (isset($this->links[$code])) {
            $linkItem =$this->links[$code];$linkItem->incrementClick(); // อัปเดตสถิติ
            $this->saveToStorage();
            return $linkItem->getOriginalUrl();
        }
        return null;
    }

    public function getAllLinks(): array {
        $list = array_values($this->links);
        usort($list, fn($a,$b) => strcmp($b->getCreatedAt(),$a->getCreatedAt()));
        return $list;
    }

    public function deleteLink(string $code): bool {
        if (isset($this->links[$code])) {
            unset($this->links[$code]);$this->saveToStorage();
            return true;
        }
        return false;
    }

    private function saveToStorage(): void {
        file_put_contents($this->storageFile, json_encode($this->links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function loadFromStorage(): void {
        if (file_exists($this->storageFile)) {
            $content = file_get_contents($this->storageFile);
            $data = json_decode($content, true);
            if (is_array($data)) {
                foreach ($data as$code => $itemData) {$this->links[$code] = LinkItem::fromArray($itemData);
                }
            }
        }
    }
}

// =========================================================================
// ROUTER & API CONTROLLER
// =========================================================================
$manager = new RedirectManager(__DIR__ . '/links.json');

// 1. Redirect เมื่อมีคนกดลิงก์สั้น (?c=XXXXXX)
if (isset($_GET['c'])) {$code = trim($_GET['c']);$targetUrl = $manager->handleRedirect($code);

    if ($targetUrl) {
        header("Location: " . $targetUrl, true, 302);
        exit;
    } else {
        http_response_code(404);
        die("ไม่พบ Short Code [ {$code} ] ในระบบ");
    }
}

// 2. API endpoint สำหรับการโหลดข้อมูลตารางแบบ Real-time
if (isset($_GET['api']) &&$_GET['api'] === 'list') {
    header('Content-Type: application/json');
    echo json_encode($manager->getAllLinks());
    exit;
}

// 3. API endpoint สำหรับสร้างลิงก์ย่อผ่าน AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    if (isset($input['action']) &&$input['action'] === 'shorten') {
        $url = trim($input['url'] ?? '');
        if (!empty($url)) {
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "https://" . $url;
            }
            $newLink = $manager->createShortLink($url);
            echo json_encode(['success' => true, 'data' => $newLink]);
        } else {
            echo json_encode(['success' => false, 'error' => 'กรุณาระบุ URL']);
        }
        exit;
    }
}

// 4. API endpoint สำหรับลบลิงก์ผ่าน AJAX
if (isset($_GET['api']) && $_GET['api'] === 'delete' && isset($_GET['code'])) {
    header('Content-Type: application/json');
    $code = trim($_GET['code']);$success = $manager->deleteLink($code);
    echo json_encode(['success' => $success]);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) &&$_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$baseUrl =$protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP OOP Real-time - URL Shortener Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #0f172a; color: #f8fafc; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="p-4 md:p-8 min-h-screen">

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <header class="text-center space-y-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-500/20 text-indigo-400 rounded-full text-xs font-semibold border border-indigo-500/30">
                <i class="fa-brands fa-php text-lg"></i> PHP OOP + Real-time Syncing
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            </span>
            <h1 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">
                ระบบย่อลิงก์ & สถิติแบบ Real-time
            </h1>
            <p class="text-slate-400 text-sm">
                3 คลาสหลัก: <code class="text-indigo-300">UrlEncoder</code>, <code class="text-purple-300">LinkItem</code>, <code class="text-pink-300">RedirectManager</code>
            </p>
        </header>

        <!-- Notification Toast -->
        <div id="toast" class="hidden p-4 rounded-xl flex items-center gap-3 transition-all">
            <i id="toastIcon" class="text-xl"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- Form Input -->
        <section class="glass rounded-2xl p-6 shadow-xl">
            <form id="shortenForm" class="space-y-3">
                <label class="block text-sm font-medium text-slate-300">ใส่ URL ต้นฉบับที่ต้องการย่อ</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="url" id="urlInput" required placeholder="https://example.com/very-long-path..." 
                        class="flex-grow px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-6 py-3 rounded-xl flex items-center justify-center gap-2 transition-all cursor-pointer">
                        <i class="fa-solid fa-scissors"></i>
                        <span>ย่อลิงก์</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- Table Stats -->
        <section class="glass rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-700 pb-3">
                <h2 class="text-lg font-bold flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-indigo-400"></i>
                    สถิติการใช้งานแบบ Real-time
                </h2>
                <span id="linkCountBadge" class="bg-indigo-500/20 text-indigo-300 text-xs px-3 py-1 rounded-full border border-indigo-500/30">
                    0 ลิงก์
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-800 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">URL ต้นฉบับ</th>
                            <th class="py-3 px-4 text-center">จำนวนคลิก</th>
                            <th class="py-3 px-4 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="linksTableBody" class="divide-y divide-slate-800">
                        <!-- Render Real-time via JavaScript -->
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Real-time Polling JavaScript -->
    <script>
        const baseUrl = <?= json_encode($baseUrl) ?>;

        // ดึงข้อมูลลิงก์แบบ Real-time จาก PHP API
        async function fetchLinksRealtime() {
            try {
                const res = await fetch('index.php?api=list');
                const links = await res.json();
                renderTable(links);
            } catch (err) {
                console.error('Realtime sync error:', err);
            }
        }

        // วาดตารางข้อมูล
        function renderTable(links) {
            const tbody = document.getElementById('linksTableBody');
            document.getElementById('linkCountBadge').textContent = `${links.length} ลิงก์`;

            if (links.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-500">
                            ยังไม่มีข้อมูลลิงก์ในระบบ กรุณาสร้างลิงก์ย่อแรกของคุณ
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = links.map(link => {
                const shortUrl = `${baseUrl}?c=${link.shortCode}`;
                return `
                    <tr class="hover:bg-slate-800/50 transition-colors">
                        <td class="py-3 px-4 font-mono text-indigo-400 font-semibold">
                            <span class="bg-indigo-950 px-2.5 py-1 rounded border border-indigo-800/60">
                                ${link.shortCode}
                            </span>
                        </td>
                        <td class="py-3 px-4 max-w-xs truncate text-slate-300" title="${link.originalUrl}">
                            ${link.originalUrl}
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 rounded-full border border-emerald-500/20 font-bold text-xs transition-all">
                                <i class="fa-solid fa-mouse-pointer mr-1 text-xs"></i> ${link.clicks} คลิก
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="copyLink('${shortUrl}')" class="p-2 bg-slate-700 hover:bg-indigo-600 rounded-lg text-white" title="คัดลอกลิงก์">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                                <a href="${shortUrl}" target="_blank" class="p-2 bg-slate-700 hover:bg-emerald-600 rounded-lg text-white" title="ทดสอบเปิดลิงก์">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                                <button onclick="deleteLink('${link.shortCode}')" class="p-2 bg-slate-700 hover:bg-rose-600 rounded-lg text-white" title="ลบลิงก์">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // จัดการส่งฟอร์มสร้างลิงก์ผ่าน AJAX
        document.getElementById('shortenForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const urlInput = document.getElementById('urlInput');
            const url = urlInput.value.trim();

            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'shorten', url: url })
            });

            const result = await res.json();
            if (result.success) {
                showToast(`สร้าง Short Code [ ${result.data.shortCode} ] เรียบร้อย!`);
                urlInput.value = '';
                fetchLinksRealtime(); // อัปเดตทันที
            } else {
                showToast(result.error, true);
            }
        });

        // จัดการลบลิงก์ผ่าน AJAX
        async function deleteLink(code) {
            if (!confirm(`ยืนยันการลบ Short Code [ ${code} ] ?`)) return;

            const res = await fetch(`index.php?api=delete&code=${code}`);
            const result = await res.json();
            if (result.success) {
                showToast(`ลบลิงก์ [ ${code} ] สำเร็จ`);
                fetchLinksRealtime(); // อัปเดตทันที
            }
        }

        // ฟังก์ชันคัดลอกลิงก์
        function copyLink(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('คัดลอก Short URL เรียบร้อยแล้ว!');
            });
        }

        // แสดงแจ้งเตือน Toast
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toastMsg');
            const toastIcon = document.getElementById('toastIcon');

            toastMsg.textContent = message;
            if (isError) {
                toast.className = "p-4 rounded-xl flex items-center gap-3 bg-rose-500/20 border border-rose-500/40 text-rose-300";
                toastIcon.className = "fa-solid fa-circle-exclamation";
            } else {
                toast.className = "p-4 rounded-xl flex items-center gap-3 bg-emerald-500/20 border border-emerald-500/40 text-emerald-300";
                toastIcon.className = "fa-solid fa-circle-check";
            }

            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        // โหลดข้อมูลครั้งแรก
        fetchLinksRealtime();

        // 🔄 เริ่มระบบ Auto-Polling ดึงข้อมูลใหม่ทุกๆ 2 วินาที (Real-time Syncing)
        setInterval(fetchLinksRealtime, 2000);
    </script>
</body>
</html>