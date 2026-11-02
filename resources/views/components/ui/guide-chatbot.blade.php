{{-- ==================== NEXABOT AI GUIDE CHATBOT WIDGET ==================== --}}
<div id="nexabotWidgetContainer" class="fixed bottom-5 right-5 z-50 select-none font-sans print:hidden">

    {{-- 1. Floating Toggle Trigger Button --}}
    <button
        type="button"
        id="nexabotToggleBtn"
        onclick="toggleNexabot()"
        class="group relative flex items-center gap-2.5 px-4 py-3 rounded-full bg-gradient-to-r from-indigo-600 via-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white shadow-xl shadow-indigo-500/30 hover:shadow-indigo-500/40 hover:scale-105 active:scale-95 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
        aria-label="Buka Panduan NexaBot AI"
    >
        <div class="relative flex items-center justify-center">
            <i data-lucide="sparkles" class="w-5 h-5 text-amber-300 animate-pulse"></i>
            <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-indigo-600"></span>
        </div>
        <span class="font-bold text-xs tracking-tight">Tanya NexaBot</span>
    </button>

    {{-- 2. Floating Chat Window --}}
    <div
        id="nexabotWindow"
        class="hidden fixed bottom-5 right-3 sm:right-5 w-[calc(100vw-1.5rem)] sm:w-[400px] md:w-[430px] h-[580px] max-h-[82vh] bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col transition-all duration-300 animate-scale-in"
    >
        {{-- Chat Header --}}
        <div class="p-3.5 px-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between border-b border-indigo-900/40 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="relative w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/30 shrink-0">
                    <i data-lucide="bot" class="w-4 h-4"></i>
                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-slate-900"></span>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h4 class="font-bold text-xs tracking-tight text-white">NexaBot AI</h4>
                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded-md bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                            Panduan
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-300">Asisten Resmi NexaPOS</p>
                </div>
            </div>

            <div class="flex items-center gap-1 text-slate-300">
                <button
                    type="button"
                    onclick="clearNexabotHistory()"
                    class="p-1.5 rounded-lg hover:bg-white/10 hover:text-white transition-colors"
                    title="Bersihkan Percakapan"
                    aria-label="Bersihkan Percakapan"
                >
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </button>
                <button
                    type="button"
                    onclick="toggleNexabot()"
                    class="p-1.5 rounded-lg hover:bg-white/10 hover:text-white transition-colors"
                    title="Tutup Chat"
                    aria-label="Tutup Chat"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        {{-- Chat Messages Area --}}
        <div id="nexabotMessages" class="flex-1 overflow-y-auto p-3.5 space-y-3 text-xs bg-slate-50/60 dark:bg-slate-950/40 select-text">
            {{-- Welcome Card --}}
            <div id="nexabotWelcomeCard" class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-2.5">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 dark:text-slate-100 text-xs">Halo! Saya NexaBot 👋</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                            Saya siap memandu seluruh fitur NexaPOS (Kasir POS, Stok, Resep & HPP, Promo, Laporan, dll). Ada yang ingin Anda tanyakan?
                        </p>
                    </div>
                </div>

                {{-- Quick Prompt Chips --}}
                <div class="pt-1.5 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500">Pertanyaan Populer:</span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onclick="sendQuickPrompt('Bagaimana cara menghitung HPP dan menyusun resep produk?')"
                            class="text-left text-[11px] px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-700 text-slate-700 dark:text-slate-300 transition-colors"
                        >
                            💡 Cara hitung HPP resep?
                        </button>
                        <button
                            type="button"
                            onclick="sendQuickPrompt('Bagaimana cara buka shift dan melayani transaksi di Zona Kasir?')"
                            class="text-left text-[11px] px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-700 text-slate-700 dark:text-slate-300 transition-colors"
                        >
                            🛒 Cara pakai Zona Kasir POS?
                        </button>
                        <button
                            type="button"
                            onclick="sendQuickPrompt('Apa perbedaan Produk Siap Jual, Persediaan Bahan Baku, dan Layanan?')"
                            class="text-left text-[11px] px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-700 text-slate-700 dark:text-slate-300 transition-colors"
                        >
                            📦 Beda Produk & Persediaan?
                        </button>
                        <button
                            type="button"
                            onclick="sendQuickPrompt('Bagaimana alur melakukan Stock Opname di gudang?')"
                            class="text-left text-[11px] px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-700 text-slate-700 dark:text-slate-300 transition-colors"
                        >
                            📋 Alur Stock Opname?
                        </button>
                    </div>
                </div>
            </div>

            {{-- Message list container --}}
            <div id="nexabotHistoryList" class="space-y-3">
                {{-- Injected dynamically --}}
            </div>

            {{-- Typing Indicator --}}
            <div id="nexabotTypingIndicator" class="hidden flex items-start gap-2 pt-1">
                <div class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <i data-lucide="bot" class="w-3.5 h-3.5"></i>
                </div>
                <div class="p-2.5 px-3 rounded-2xl rounded-tl-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.2s]"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.4s]"></span>
                </div>
            </div>
        </div>

        {{-- Chat Input Form --}}
        <div class="p-3 bg-white dark:bg-slate-900 border-t border-slate-200/90 dark:border-slate-800 shrink-0">
            <form id="nexabotForm" onsubmit="handleNexabotSubmit(event)" class="relative flex items-center gap-1.5">
                <input
                    type="text"
                    id="nexabotInput"
                    placeholder="Tanyakan panduan NexaPOS..."
                    autocomplete="off"
                    maxlength="1000"
                    class="flex-1 pl-3 pr-10 py-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
                />
                <button
                    type="submit"
                    id="nexabotSendBtn"
                    class="absolute right-1.5 p-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-transform active:scale-90 disabled:opacity-40 disabled:pointer-events-none"
                    aria-label="Kirim Pertanyaan"
                >
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>
            <div class="mt-1 px-1 flex items-center justify-between text-[10px] text-slate-400">
                <span>Panduan khusus NexaPOS</span>
                <span>Enter untuk kirim</span>
            </div>
        </div>
    </div>
</div>

{{-- Chatbot Logic --}}
<script>
    (function() {
        const STORAGE_KEY = 'nexapos_nexabot_history_v1';
        let chatHistory = [];
        let isSending = false;

        // Simple Markdown Formatter Helper
        function formatMarkdown(text) {
            if (!text) return '';
            let html = text
                // Escape raw HTML tags
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                // Code block / inline code
                .replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-[10px] text-indigo-600 dark:text-indigo-400 font-bold">$1</code>')
                // Bold: **text**
                .replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-slate-900 dark:text-white">$1</strong>')
                // Italic: *text*
                .replace(/\*([^*]+)\*/g, '<em class="italic">$1</em>')
                // Line breaks
                .replace(/\n\n/g, '<br><br>')
                .replace(/\n/g, '<br>');

            return html;
        }

        function loadHistory() {
            try {
                const saved = sessionStorage.getItem(STORAGE_KEY);
                if (saved) {
                    chatHistory = JSON.parse(saved);
                }
            } catch (e) {
                chatHistory = [];
            }
        }

        function saveHistory() {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(chatHistory.slice(-16)));
            } catch (e) {}
        }

        function renderMessages() {
            const container = document.getElementById('nexabotHistoryList');
            if (!container) return;
            container.innerHTML = '';

            chatHistory.forEach(item => {
                const bubble = document.createElement('div');
                if (item.role === 'user') {
                    bubble.className = "flex justify-end";
                    bubble.innerHTML = `
                        <div class="max-w-[85%] p-2.5 px-3 rounded-2xl rounded-tr-sm bg-indigo-600 text-white shadow-xs">
                            <p class="leading-relaxed whitespace-pre-wrap">${item.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
                        </div>
                    `;
                } else {
                    bubble.className = "flex items-start gap-2";
                    bubble.innerHTML = `
                        <div class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="bot" class="w-3.5 h-3.5"></i>
                        </div>
                        <div class="max-w-[88%] p-3 rounded-2xl rounded-tl-sm bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-slate-700 dark:text-slate-200 leading-relaxed">
                            <div>${formatMarkdown(item.content)}</div>
                        </div>
                    `;
                }
                container.appendChild(bubble);
            });

            if (typeof window.initIcons === 'function') {
                window.initIcons();
            }

            scrollToBottom();
        }

        function scrollToBottom() {
            const messagesEl = document.getElementById('nexabotMessages');
            if (messagesEl) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        }

        function toggleNexabot() {
            const win = document.getElementById('nexabotWindow');
            if (!win) return;
            const isHidden = win.classList.contains('hidden');
            if (isHidden) {
                win.classList.remove('hidden');
                setTimeout(() => {
                    const inp = document.getElementById('nexabotInput');
                    if (inp) inp.focus();
                    scrollToBottom();
                }, 100);
            } else {
                win.classList.add('hidden');
            }
        }
        window.toggleNexabot = toggleNexabot;

        function clearNexabotHistory() {
            if (confirm('Bersihkan riwayat percakapan NexaBot?')) {
                chatHistory = [];
                sessionStorage.removeItem(STORAGE_KEY);
                renderMessages();
            }
        }
        window.clearNexabotHistory = clearNexabotHistory;

        function sendQuickPrompt(promptText) {
            const input = document.getElementById('nexabotInput');
            if (input) {
                input.value = promptText;
                submitMessage(promptText);
            }
        }
        window.sendQuickPrompt = sendQuickPrompt;

        async function submitMessage(userText) {
            if (isSending || !userText || !userText.trim()) return;

            const text = userText.trim();
            isSending = true;

            const input = document.getElementById('nexabotInput');
            const sendBtn = document.getElementById('nexabotSendBtn');
            const typing = document.getElementById('nexabotTypingIndicator');

            if (input) input.value = '';
            if (sendBtn) sendBtn.disabled = true;

            // Append user message
            chatHistory.push({ role: 'user', content: text });
            saveHistory();
            renderMessages();

            if (typing) typing.classList.remove('hidden');
            scrollToBottom();

            try {
                const response = await fetch("{{ route('guide-chat.message') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        message: text,
                        history: chatHistory.slice(-8),
                        current_path: window.location.pathname,
                        page_title: document.title
                    })
                });

                const data = await response.json();
                const reply = data.reply || 'Maaf, tidak dapat menerima jawaban.';

                chatHistory.push({ role: 'assistant', content: reply });
                saveHistory();
                renderMessages();
            } catch (err) {
                chatHistory.push({
                    role: 'assistant',
                    content: 'Maaf, terjadi kesalahan koneksi jaringan. Silakan coba kembali.'
                });
                saveHistory();
                renderMessages();
            } finally {
                isSending = false;
                if (typing) typing.classList.add('hidden');
                if (sendBtn) sendBtn.disabled = false;
                if (input) input.focus();
                scrollToBottom();
            }
        }

        function handleNexabotSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('nexabotInput');
            if (input) {
                submitMessage(input.value);
            }
        }
        window.handleNexabotSubmit = handleNexabotSubmit;

        document.addEventListener('DOMContentLoaded', () => {
            loadHistory();
            renderMessages();

            // Keyboard shortcut Alt + / to toggle NexaBot
            document.addEventListener('keydown', (e) => {
                if (e.altKey && e.key === '/') {
                    e.preventDefault();
                    toggleNexabot();
                }
            });
        });
    })();
</script>

