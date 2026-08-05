const { createApp, ref, onMounted, computed } = Vue;

const App = {
    template: `
        <div class="min-h-screen pb-24">
            <!-- Header -->
            <header class="glass-card sticky top-0 z-50 px-4 py-4 mb-6 rounded-b-2xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-sky-500 flex items-center justify-center text-xl font-bold shadow-lg shadow-sky-500/30">
                            🤖
                        </div>
                        <div>
                            <h1 class="text-sm font-bold leading-tight">AI Kanal Manager</h1>
                            <p class="text-xs text-sky-400 font-medium">Boshqaruv Paneli</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                            {{ summary.plan ? summary.plan.toUpperCase() : 'FREE' }}
                        </span>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="px-4">
                <!-- Loader -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-20 space-y-4">
                    <div class="w-12 h-12 border-4 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm text-slate-400">Yuklanmoqda...</p>
                </div>

                <!-- Alert Messages -->
                <div v-if="error" class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                    ⚠️ {{ error }}
                </div>
                <div v-if="message" class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
                    ✅ {{ message }}
                </div>

                <div v-show="!loading">
                    <!-- 1. CALENDAR VIEW -->
                    <div v-if="activeTab === 'calendar'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-slate-200">📅 Postlar Taqrimi</h2>
                            <span class="text-xs text-slate-400">{{ posts.length }} ta post</span>
                        </div>

                        <div v-if="posts.length === 0" class="glass-card rounded-2xl p-8 text-center text-slate-400 text-sm">
                            Hozircha rejalashtirilgan postlar mavjud emas. Bot chatida yangi post yozing.
                        </div>

                        <div class="space-y-4">
                            <div v-for="post in posts" :key="post.id" class="glass-card rounded-2xl p-4 transition duration-300 hover:border-sky-500/30">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-slate-400 font-medium">📺 {{ post.channel_title }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="getStatusClass(post.status)">
                                        {{ getStatusLabel(post.status) }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-200 line-clamp-3 mb-3 whitespace-pre-wrap">{{ post.final_content }}</p>
                                <div class="flex items-center justify-between border-t border-slate-700/50 pt-3">
                                    <span class="text-xs text-slate-400 flex items-center">
                                        ⏰ {{ post.scheduled_at ? formatDate(post.scheduled_at) : 'Rejalashtirilmagan' }}
                                    </span>
                                    <button @click="openEditPost(post)" class="text-xs font-semibold text-sky-400 hover:text-sky-300">
                                        ✏️ Tahrirlash
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. EDIT POST VIEW -->
                    <div v-if="activeTab === 'edit-post'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-slate-200">✏️ Postni Tahrirlash</h2>
                            <button @click="activeTab = 'calendar'" class="text-xs text-slate-400 hover:text-white">Orqaga</button>
                        </div>

                        <div class="glass-card rounded-2xl p-4 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1.5 uppercase">Post Matni</label>
                                <textarea v-model="editForm.final_content" rows="8" class="w-full glass-input rounded-xl p-3 text-sm focus:ring-1 focus:ring-sky-500"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5 uppercase">Holat</label>
                                    <select v-model="editForm.status" class="w-full glass-input rounded-xl p-3 text-sm">
                                        <option value="draft">Draft (Qoralama)</option>
                                        <option value="scheduled">Rejalashtirilgan</option>
                                        <option value="posted">Joylashtirilgan</option>
                                        <option value="failed">Rad etilgan</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5 uppercase">Rejalashtirilgan Vaqt</label>
                                    <input type="datetime-local" v-model="editForm.scheduled_local" class="w-full glass-input rounded-xl p-3 text-sm">
                                </div>
                            </div>

                            <button @click="savePost" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-white font-bold rounded-xl shadow-lg shadow-sky-500/25 transition duration-300">
                                💾 O'zgarishlarni Saqlash
                              </button>
                        </div>
                    </div>

                    <!-- 3. CHANNELS VIEW -->
                    <div v-if="activeTab === 'channels'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-slate-200">📺 Ulangan Kanallar</h2>
                        </div>

                        <div v-if="channels.length === 0" class="glass-card rounded-2xl p-8 text-center text-slate-400 text-sm">
                            Ulagan kanallaringiz ro'yxati bo'sh. Telegram chatida /mychannels buyrug'ini ishlating.
                        </div>

                        <div class="space-y-4">
                            <div v-for="channel in channels" :key="channel.id" class="glass-card rounded-2xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="font-bold text-slate-200">{{ channel.title }}</h3>
                                        <p class="text-xs text-sky-400 font-medium">@{{ channel.username || 'yopiq_kanal' }}</p>
                                    </div>
                                    <span class="w-2.5 h-2.5 rounded-full" :class="channel.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                </div>

                                <div class="border-t border-slate-700/50 pt-3 space-y-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase">Shablon Hashtaglar (ajratib yozing)</label>
                                        <input type="text" v-model="channel.settings_hashtags" placeholder="#uy #avto" class="w-full glass-input rounded-lg px-3 py-1.5 text-xs">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase">Format</label>
                                            <select v-model="channel.settings_format" class="w-full glass-input rounded-lg px-3 py-1.5 text-xs">
                                                <option value="default">Standart (default)</option>
                                                <option value="bold">Qalin (Bold)</option>
                                                <option value="italic">Kursiv (Italic)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase">Avto-o'chirish (soat)</label>
                                            <input type="number" v-model="channel.settings_auto_delete" class="w-full glass-input rounded-lg px-3 py-1.5 text-xs">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase">AI maxsus shabloni / Yo’riqnomasi</label>
                                        <textarea v-model="channel.settings_template" placeholder="Masalan: Har doim postni quyidagi shablon bo’yicha tayyorla: ..." rows="3" class="w-full glass-input rounded-lg px-3 py-1.5 text-xs"></textarea>
                                    </div>
                                    <button @click="saveChannel(channel)" class="w-full mt-2 py-2 bg-sky-500/20 hover:bg-sky-500/30 text-sky-400 font-bold rounded-lg text-xs transition duration-300">
                                        💾 Sozlamalarni Saqlash
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. STATS VIEW -->
                    <div v-if="activeTab === 'stats'">
                        <h2 class="text-lg font-bold text-slate-200 mb-4">📊 Statistika va Limitlar</h2>

                        <!-- Limits Progress -->
                        <div class="glass-card rounded-2xl p-4 mb-6 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-400">Kunlik AI limit</span>
                                <span class="font-bold text-slate-200">{{ summary.daily_used }} / {{ summary.daily_limit }} post</span>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-2">
                                <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" :style="{ width: limitProgress + '%' }"></div>
                            </div>
                            <p class="text-[10px] text-slate-400">Kunlik limit har kuni Toshkent vaqti bilan 00:00 da yangilanadi.</p>
                        </div>

                        <!-- Counters -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="glass-card rounded-2xl p-4 text-center">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Joylashtirilgan postlar</span>
                                <p class="text-2xl font-bold text-slate-100 mt-1">{{ summary.published_posts }} ta</p>
                            </div>
                            <div class="glass-card rounded-2xl p-4 text-center">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Rejalashtirilgan postlar</span>
                                <p class="text-2xl font-bold text-slate-100 mt-1">{{ summary.scheduled_posts }} ta</p>
                            </div>
                        </div>

                        <!-- Chart container -->
                        <div class="glass-card rounded-2xl p-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase mb-3">So'nggi 7 kunlik AI ishlatilishi</h3>
                            <canvas id="usageChart" height="200"></canvas>
                        </div>
                    </div>

                    <!-- 5. OPERATORS VIEW -->
                    <div v-if="activeTab === 'operators'">
                        <h2 class="text-lg font-bold text-slate-200 mb-4">💼 Biznes: Operatorlar rollari</h2>

                        <div v-if="summary.plan !== 'business'" class="glass-card rounded-2xl p-6 text-center space-y-4">
                            <div class="text-4xl text-center">🔒</div>
                            <h3 class="font-bold text-slate-200">Biznes Tarif Kerak</h3>
                            <p class="text-xs text-slate-400">Ko'p operatorli boshqaruv va kanallarga alohida rollar biriktirish Biznes obunachilar uchun ochiq.</p>
                            <button @click="triggerPayCommand" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-white font-bold rounded-lg text-xs transition duration-300">
                                💎 Upgrade qilish
                            </button>
                        </div>

                        <div v-else class="glass-card rounded-2xl p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-sm font-bold text-slate-200">Operatorlar ro'yxati</h3>
                                <button class="text-xs font-semibold text-sky-400 hover:text-sky-300">➕ Qo'shish</button>
                            </div>
                            <div class="divide-y divide-slate-700/50">
                                <div v-for="op in operators" :key="op.id" class="py-3 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-slate-200">{{ op.name }}</p>
                                        <p class="text-xs text-slate-400">@{{ op.username }}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                        {{ op.role.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Bottom Navigation Bar -->
            <nav class="glass-card fixed bottom-0 left-0 right-0 z-50 flex items-center justify-around py-3 rounded-t-2xl border-t border-slate-700/50">
                <button @click="switchTab('calendar')" :class="activeTab === 'calendar' || activeTab === 'edit-post' ? 'text-sky-400' : 'text-slate-400'" class="flex flex-col items-center">
                    <span class="text-xl">📅</span>
                    <span class="text-[10px] font-medium mt-1">Taqvim</span>
                </button>
                <button @click="switchTab('channels')" :class="activeTab === 'channels' ? 'text-sky-400' : 'text-slate-400'" class="flex flex-col items-center">
                    <span class="text-xl">📺</span>
                    <span class="text-[10px] font-medium mt-1">Kanallar</span>
                </button>
                <button @click="switchTab('stats')" :class="activeTab === 'stats' ? 'text-sky-400' : 'text-slate-400'" class="flex flex-col items-center">
                    <span class="text-xl">📊</span>
                    <span class="text-[10px] font-medium mt-1">Statistika</span>
                </button>
                <button @click="switchTab('operators')" :class="activeTab === 'operators' ? 'text-sky-400' : 'text-slate-400'" class="flex flex-col items-center">
                    <span class="text-xl">💼</span>
                    <span class="text-[10px] font-medium mt-1">Operatorlar</span>
                </button>
            </nav>
        </div>
    `,
    setup() {
        const loading = ref(true);
        const activeTab = ref('calendar');
        const posts = ref([]);
        const channels = ref([]);
        const operators = ref([]);
        const error = ref(null);
        const message = ref(null);

        const summary = ref({
            published_posts: 0,
            scheduled_posts: 0,
            plan: 'free',
            daily_used: 0,
            daily_limit: 5
        });

        const editForm = ref({
            id: null,
            final_content: '',
            status: 'draft',
            scheduled_local: ''
        });

        // Computed Progress Percentage
        const limitProgress = computed(() => {
            const limit = summary.value.daily_limit || 5;
            const used = summary.value.daily_used || 0;
            return Math.min(Math.round((used / limit) * 100), 100);
        });

        // Telegram WebApp raw initData
        const tgInitData = window.Telegram?.WebApp?.initData || '';

        // Helper to format date
        const formatDate = (isoString) => {
            if (!isoString) return '';
            const d = new Date(isoString);
            return d.toLocaleString('uz-UZ', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const getStatusLabel = (status) => {
            return {
                'draft': 'Qoralama',
                'scheduled': 'Rejalangan',
                'posted': 'Joylandi',
                'failed': 'Xato'
            }[status] || status;
        };

        const getStatusClass = (status) => {
            return {
                'draft': 'bg-slate-700 text-slate-300',
                'scheduled': 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                'posted': 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                'failed': 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
            }[status] || 'bg-slate-700 text-slate-300';
        };

        // HTTP request wrapper with secure headers
        const apiRequest = async (url, method = 'GET', data = null) => {
            const headers = {
                'Content-Type': 'application/json',
                'X-Telegram-Init-Data': tgInitData
            };
            
            const options = { method, headers };
            if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const json = await response.json();
            
            if (!response.ok) {
                throw new Error(json.message || 'Xatolik yuz berdi.');
            }
            return json;
        };

        const loadData = async () => {
            loading.value = true;
            error.value = null;
            try {
                // Load Posts
                const postsRes = await apiRequest('/api/mini-app/posts');
                posts.value = postsRes.posts;

                // Load Channels
                const channelsRes = await apiRequest('/api/mini-app/channels');
                channels.value = channelsRes.channels.map(ch => ({
                    ...ch,
                    settings_hashtags: ch.settings?.hashtags || '',
                    settings_format: ch.settings?.format_style || 'default',
                    settings_auto_delete: ch.settings?.auto_delete_hours || 0,
                    settings_template: ch.settings?.custom_template || '',
                }));

                // Load Stats summary
                const statsRes = await apiRequest('/api/mini-app/stats');
                summary.value = statsRes.summary;

                // Load chart data
                setTimeout(() => {
                    renderChart(statsRes.charts.ai_usage);
                }, 100);

                // Load Operators
                if (summary.value.plan === 'business') {
                    const opsRes = await apiRequest('/api/mini-app/business/operators');
                    operators.value = opsRes.operators;
                }

            } catch (err) {
                error.value = err.message;
            } finally {
                loading.value = false;
            }
        };

        // Render Chart using Chart.js
        let chartInstance = null;
        const renderChart = (chartData) => {
            const ctx = document.getElementById('usageChart');
            if (!ctx) return;

            if (chartInstance) {
                chartInstance.destroy();
            }

            const dates = chartData.map(d => d.date);
            const requests = chartData.map(d => d.total_requests);

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates.length ? dates : ['Bugun'],
                    datasets: [{
                        label: 'AI So\'rovlari soni',
                        data: requests.length ? requests : [0],
                        backgroundColor: '#38bdf8',
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#94a3b8' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8' }
                        }
                    }
                }
            });
        };

        const switchTab = (tab) => {
            activeTab.value = tab;
            message.value = null;
            error.value = null;
            if (tab === 'stats' || tab === 'calendar') {
                loadData();
            }
        };

        // Edit Post
        const openEditPost = (post) => {
            editForm.value.id = post.id;
            editForm.value.final_content = post.final_content;
            editForm.value.status = post.status;
            
            // Format to yyyy-MM-ddThh:mm
            if (post.scheduled_at) {
                const date = new Date(post.scheduled_at);
                const offset = date.getTimezoneOffset();
                date.setMinutes(date.getMinutes() - offset);
                editForm.value.scheduled_local = date.toISOString().slice(0, 16);
            } else {
                editForm.value.scheduled_local = '';
            }

            activeTab.value = 'edit-post';
        };

        const savePost = async () => {
            message.value = null;
            error.value = null;
            try {
                const url = `/api/mini-app/posts/${editForm.value.id}/edit`;
                let isoTime = null;
                
                if (editForm.value.scheduled_local) {
                    isoTime = new Date(editForm.value.scheduled_local).toISOString();
                }

                await apiRequest(url, 'POST', {
                    final_content: editForm.value.final_content,
                    status: editForm.value.status,
                    scheduled_at: isoTime
                });

                message.value = 'Post muvaffaqiyatli saqlandi!';
                activeTab.value = 'calendar';
                loadData();
            } catch (err) {
                error.value = err.message;
            }
        };

        // Channel save settings
        const saveChannel = async (channel) => {
            message.value = null;
            error.value = null;
            try {
                const url = `/api/mini-app/channels/${channel.id}/settings`;
                await apiRequest(url, 'POST', {
                    hashtags: channel.settings_hashtags,
                    format_style: channel.settings_format,
                    auto_delete_hours: parseInt(channel.settings_auto_delete) || 0,
                    custom_template: channel.settings_template
                });
                message.value = `"${channel.title}" sozlamalari muvaffaqiyatli saqlandi!`;
            } catch (err) {
                error.value = err.message;
            }
        };

        const triggerPayCommand = () => {
            // Close Web App and call Telegram payment trigger
            window.Telegram?.WebApp?.close();
        };

        onMounted(() => {
            // Notify Telegram WebApp is ready
            window.Telegram?.WebApp?.ready();
            window.Telegram?.WebApp?.expand(); // expand to full screen height
            loadData();
        });

        return {
            loading,
            activeTab,
            posts,
            channels,
            operators,
            error,
            message,
            summary,
            editForm,
            limitProgress,
            formatDate,
            getStatusLabel,
            getStatusClass,
            switchTab,
            openEditPost,
            savePost,
            saveChannel,
            triggerPayCommand
        };
    }
};

createApp(App).mount('#app');
