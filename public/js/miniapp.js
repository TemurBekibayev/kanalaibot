const { createApp, ref, onMounted, computed } = Vue;

const App = {
    template: `
        <div class="min-h-screen pb-32 pt-4 px-4 bg-wallet-bg text-white">
            <!-- Main Content Area -->
            <main>
                <!-- Loader -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-32 space-y-4">
                    <div class="w-10 h-10 border-3 border-wallet-blue border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs text-wallet-textSecondary font-medium">Ma'lumotlar yuklanmoqda...</p>
                </div>

                <!-- Alert Messages -->
                <div v-if="error" class="mb-4 p-3.5 rounded-xl bg-wallet-red/10 border border-wallet-red/20 text-wallet-red text-xs leading-normal flex items-start space-x-2">
                    <span>⚠️</span>
                    <span>{{ error }}</span>
                </div>
                <div v-if="message" class="mb-4 p-3.5 rounded-xl bg-wallet-green/10 border border-wallet-green/20 text-wallet-green text-xs leading-normal flex items-start space-x-2">
                    <span>✅</span>
                    <span>{{ message }}</span>
                </div>

                <div v-show="!loading">
                    <!-- ============================================== -->
                    <!-- 1. HOME TAB (WALLET DASHBOARD) -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'home'" class="space-y-6">
                        <!-- User Profile Header & Verification -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-wallet-blue to-sky-600 flex items-center justify-center text-sm font-bold text-white shadow-md shadow-wallet-blue/20">
                                    {{ userInitials }}
                                </div>
                                <div class="flex items-center">
                                    <span class="font-bold text-base text-white">Hamyon</span>
                                    <!-- Verified Badge -->
                                    <svg class="w-4 h-4 text-wallet-blue fill-current ml-1" viewBox="0 0 24 24">
                                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                            </div>
                            <!-- Segmented Tab Switcher (Wallet / DeFi style) -->
                            <div class="bg-[#1a222d] p-0.5 rounded-lg flex items-center border border-white/5">
                                <button @click="activeTab = 'home'" class="px-3 py-1 rounded-md text-xs font-semibold bg-wallet-pillActive text-white transition">Hamyon</button>
                                <button @click="activeTab = 'stats'" class="px-3 py-1 rounded-md text-xs font-semibold text-wallet-textSecondary hover:text-white transition">Statistika</button>
                            </div>
                        </div>

                        <!-- Balance / Limit Display -->
                        <div class="text-center py-4">
                            <p class="text-xs text-wallet-textSecondary font-medium mb-1">Mavjud AI so'rovlari</p>
                            <h2 class="text-4xl font-extrabold tracking-tight text-white flex items-center justify-center">
                                {{ summary.daily_limit - summary.daily_used }}
                                <span class="text-2xl text-wallet-textSecondary font-semibold ml-1.5">ta</span>
                            </h2>
                            <div class="mt-2.5 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="summary.plan === 'business' || summary.plan === 'premium' ? 'bg-wallet-green/10 text-wallet-green border border-wallet-green/20' : 'bg-wallet-textSecondary/10 text-wallet-textSecondary border border-wallet-textSecondary/20'">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="summary.plan === 'business' || summary.plan === 'premium' ? 'bg-wallet-green' : 'bg-wallet-textSecondary'"></span>
                                Tarif: {{ summary.plan ? summary.plan.toUpperCase() : 'FREE' }}
                            </div>
                        </div>

                        <!-- Quick Action Grid (Capsule Buttons) -->
                        <div class="grid grid-cols-4 gap-2">
                            <button @click="switchTab('calendar')" class="flex flex-col items-center justify-center p-3.5 wallet-card border-none hover:bg-wallet-pillActive transition duration-200 btn-active">
                                <div class="w-11 h-11 rounded-full bg-[#1e2936] flex items-center justify-center mb-1.5">
                                    <svg class="w-5 h-5 text-wallet-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </div>
                                <span class="text-[10px] font-bold text-white">Taqvim</span>
                            </button>
                            <button @click="switchTab('channels')" class="flex flex-col items-center justify-center p-3.5 wallet-card border-none hover:bg-wallet-pillActive transition duration-200 btn-active">
                                <div class="w-11 h-11 rounded-full bg-[#1e2936] flex items-center justify-center mb-1.5">
                                    <svg class="w-5 h-5 text-wallet-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path d="M11 5L6 9H2v6h4l5 4V5z"></path>
                                        <path d="M23 9c0 0-1.8 1.5-1.8 3s1.8 3 1.8 3"></path>
                                        <path d="M19 6c0 0-3 2.7-3 6s3 6 3 6"></path>
                                    </svg>
                                </div>
                                <span class="text-[10px] font-bold text-white">Kanallar</span>
                            </button>
                            <button @click="switchTab('stats')" class="flex flex-col items-center justify-center p-3.5 wallet-card border-none hover:bg-wallet-pillActive transition duration-200 btn-active">
                                <div class="w-11 h-11 rounded-full bg-[#1e2936] flex items-center justify-center mb-1.5">
                                    <svg class="w-5 h-5 text-wallet-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <line x1="18" y1="20" x2="18" y2="10"></line>
                                        <line x1="12" y1="20" x2="12" y2="4"></line>
                                        <line x1="6" y1="20" x2="6" y2="14"></line>
                                    </svg>
                                </div>
                                <span class="text-[10px] font-bold text-white">Limitlar</span>
                            </button>
                            <button @click="triggerPremium" class="flex flex-col items-center justify-center p-3.5 wallet-card border-none hover:bg-wallet-pillActive transition duration-200 btn-active">
                                <div class="w-11 h-11 rounded-full bg-[#1e2936] flex items-center justify-center mb-1.5">
                                    <svg class="w-5 h-5 text-wallet-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5"></polygon>
                                        <polyline points="2 8.5 12 15 22 8.5"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="22"></line>
                                    </svg>
                                </div>
                                <span class="text-[10px] font-bold text-white">Tariflar</span>
                            </button>
                        </div>

                        <!-- Promotional Banner (Purple Gradient) -->
                        <div v-if="showBanner" class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-wallet-purple to-[#6236ff] p-4 shadow-lg">
                            <button @click="showBanner = false" class="absolute top-3 right-3 text-white/70 hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path d="M18 6L6 18M6 6l12 12"></path>
                                </svg>
                            </button>
                            <div class="pr-8">
                                <span class="inline-block bg-white/20 text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full mb-1.5 text-white tracking-wider">Premium AI</span>
                                <h3 class="text-sm font-bold text-white mb-0.5">Limitsiz AI Postlar Yarating</h3>
                                <p class="text-xs text-white/80 leading-normal">Maxsus shablonlar, avtomatik rejalashtirish va operatorlarni sozlash imkoniyati.</p>
                                <button @click="triggerPremium" class="mt-3 text-xs font-bold text-white bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg transition btn-active">
                                    Faollashtirish &gt;
                                </button>
                            </div>
                        </div>

                        <!-- Connected Channels (Assets list style) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-wallet-textSecondary uppercase tracking-wider">Ulangan Kanallar</span>
                                <span class="text-xs text-wallet-textSecondary font-semibold">Jami: {{ channels.length }} ta</span>
                            </div>
                            <div v-if="channels.length === 0" class="wallet-card p-6 text-center text-xs text-wallet-textSecondary">
                                Hozircha kanallar ulanmagan.
                            </div>
                            <div v-else class="wallet-card divide-y divide-white/5 overflow-hidden shadow-sm">
                                <div v-for="channel in channels" :key="channel.id" @click="openChannelSettings(channel)" class="p-3.5 flex items-center justify-between hover:bg-white/[0.02] transition cursor-pointer btn-active">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-wallet-blue to-sky-600 flex items-center justify-center font-bold text-white text-base">
                                            {{ channel.title.charAt(0) }}
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-white">{{ channel.title }}</h4>
                                            <p class="text-xs text-wallet-textSecondary font-medium">@{{ channel.username || 'yopiq_kanal' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right flex items-center space-x-2">
                                        <span class="text-xs font-semibold" :class="channel.is_active ? 'text-wallet-green' : 'text-wallet-red'">
                                            {{ channel.is_active ? 'Faol' : 'Nofaol' }}
                                        </span>
                                        <svg class="w-3.5 h-3.5 text-wallet-textSecondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Scheduled Posts (Activities list style) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-wallet-textSecondary uppercase tracking-wider">Taqvimdagi Postlar</span>
                                <span class="text-xs text-wallet-textSecondary font-semibold">Jami: {{ posts.length }} ta</span>
                            </div>
                            <div v-if="posts.length === 0" class="wallet-card p-6 text-center text-xs text-wallet-textSecondary">
                                Rejalashtirilgan postlar mavjud emas.
                            </div>
                            <div v-else class="wallet-card divide-y divide-white/5 overflow-hidden shadow-sm">
                                <div v-for="post in posts.slice(0, 3)" :key="post.id" @click="openEditPost(post)" class="p-3.5 flex items-center justify-between hover:bg-white/[0.02] transition cursor-pointer btn-active">
                                    <div class="flex items-center space-x-3 overflow-hidden mr-4">
                                        <div class="w-9 h-9 rounded-full bg-white/[0.04] flex items-center justify-center text-base">
                                            📝
                                        </div>
                                        <div class="overflow-hidden">
                                            <h4 class="text-xs font-bold text-white truncate max-w-[180px]">{{ post.final_content }}</h4>
                                            <p class="text-[10px] text-wallet-textSecondary truncate font-medium">📺 {{ post.channel_title }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold" :class="getStatusClass(post.status)">
                                            {{ getStatusLabel(post.status) }}
                                        </span>
                                        <p class="text-[9px] text-wallet-textSecondary font-medium mt-1">{{ formatDate(post.created_at || post.scheduled_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 2. CALENDAR VIEW -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'calendar'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-white">📅 Postlar Taqrimi</h2>
                            <button @click="activeTab = 'home'" class="text-xs text-wallet-blue font-semibold hover:text-white transition">Orqaga</button>
                        </div>

                        <div v-if="posts.length === 0" class="wallet-card p-8 text-center text-xs text-wallet-textSecondary">
                            Hozircha rejalashtirilgan postlar mavjud emas. Bot orqali yangi post jo'nating.
                        </div>

                        <div class="space-y-3">
                            <div v-for="post in posts" :key="post.id" class="wallet-card p-4 transition hover:border-wallet-blue/30 cursor-pointer btn-active" @click="openEditPost(post)">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-wallet-textSecondary font-semibold">📺 {{ post.channel_title }}</span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-extrabold" :class="getStatusClass(post.status)">
                                        {{ getStatusLabel(post.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-100 line-clamp-3 mb-3 whitespace-pre-wrap leading-relaxed">{{ post.final_content }}</p>
                                <div class="flex items-center justify-between border-t border-white/5 pt-3">
                                    <span class="text-[10px] text-wallet-textSecondary font-medium flex items-center">
                                        ⏰ {{ post.scheduled_at ? formatDate(post.scheduled_at) : 'Rejalashtirilmagan' }}
                                    </span>
                                    <span class="text-xs font-semibold text-wallet-blue hover:text-white transition">
                                        Tahrirlash &gt;
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 3. EDIT POST VIEW -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'edit-post'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-white">✏️ Postni Tahrirlash</h2>
                            <button @click="activeTab = 'calendar'" class="text-xs text-wallet-textSecondary font-semibold hover:text-white transition">Orqaga</button>
                        </div>

                        <div class="wallet-card p-4 space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Post Matni</label>
                                <textarea v-model="editForm.final_content" rows="7" class="w-full wallet-input rounded-xl p-3 text-xs leading-relaxed"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Holat</label>
                                    <select v-model="editForm.status" class="w-full wallet-input rounded-xl p-3 text-xs">
                                        <option value="draft">Draft (Qoralama)</option>
                                        <option value="scheduled">Rejalashtirilgan</option>
                                        <option value="posted">Joylashtirilgan</option>
                                        <option value="failed">Rad etilgan</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Rejalashtirilgan Vaqt</label>
                                    <input type="datetime-local" v-model="editForm.scheduled_local" class="w-full wallet-input rounded-xl p-3 text-xs">
                                </div>
                            </div>

                            <button @click="savePost" class="w-full py-3 bg-wallet-blue hover:bg-blue-600 text-white font-bold rounded-xl shadow-lg transition duration-200 btn-active">
                                💾 O'zgarishlarni Saqlash
                            </button>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 4. CHANNELS VIEW -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'channels'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-white">📺 Ulangan Kanallar</h2>
                            <button @click="activeTab = 'home'" class="text-xs text-wallet-blue font-semibold hover:text-white transition">Orqaga</button>
                        </div>

                        <div v-if="channels.length === 0" class="wallet-card p-8 text-center text-xs text-wallet-textSecondary">
                            Ulagan kanallaringiz ro'yxati bo'sh. Telegram chatida /mychannels buyrug'ini ishlating.
                        </div>

                        <div class="space-y-4">
                            <div v-for="channel in channels" :key="channel.id" class="wallet-card p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2.5">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-wallet-blue to-sky-600 flex items-center justify-center font-bold text-white text-sm">
                                            {{ channel.title.charAt(0) }}
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-sm text-white">{{ channel.title }}</h3>
                                            <p class="text-[10px] text-wallet-textSecondary font-semibold">@{{ channel.username || 'yopiq_kanal' }}</p>
                                        </div>
                                    </div>
                                    <span class="w-2.5 h-2.5 rounded-full" :class="channel.is_active ? 'bg-wallet-green' : 'bg-wallet-red'"></span>
                                </div>

                                <div class="border-t border-white/5 pt-3 space-y-3">
                                    <div>
                                        <label class="block text-[9px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Shablon Hashtaglar (ajratib yozing)</label>
                                        <input type="text" v-model="channel.settings_hashtags" placeholder="#uy #avto" class="w-full wallet-input rounded-xl px-3 py-2 text-xs">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[9px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Format</label>
                                            <select v-model="channel.settings_format" class="w-full wallet-input rounded-xl px-3 py-2 text-xs">
                                                <option value="default">Standart (default)</option>
                                                <option value="bold">Qalin (Bold)</option>
                                                <option value="italic">Kursiv (Italic)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">Avto-o'chirish (soat)</label>
                                            <input type="number" v-model="channel.settings_auto_delete" class="w-full wallet-input rounded-xl px-3 py-2 text-xs">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-wallet-textSecondary mb-1.5 uppercase tracking-wider">AI maxsus shabloni / Yo’riqnomasi</label>
                                        <textarea v-model="channel.settings_template" placeholder="Masalan: Har doim postni quyidagi shablon bo’yicha tayyorla: ..." rows="3" class="w-full wallet-input rounded-xl px-3 py-2 text-xs leading-normal"></textarea>
                                    </div>
                                    <button @click="saveChannel(channel)" class="w-full mt-2 py-2.5 bg-wallet-blue/10 hover:bg-wallet-blue/20 text-wallet-blue font-bold rounded-xl text-xs transition duration-200 btn-active">
                                        💾 Sozlamalarni Saqlash
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 5. STATS VIEW -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'stats'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-white">📊 Statistika va Limitlar</h2>
                            <button @click="activeTab = 'home'" class="text-xs text-wallet-blue font-semibold hover:text-white transition">Orqaga</button>
                        </div>

                        <!-- Limits Progress -->
                        <div class="wallet-card p-4 mb-4 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-wallet-textSecondary">Kunlik AI limit</span>
                                <span class="font-bold text-white">{{ summary.daily_used }} / {{ summary.daily_limit }} post</span>
                            </div>
                            <div class="w-full bg-[#1a2430] rounded-full h-2">
                                <div class="bg-wallet-blue h-2 rounded-full transition-all duration-500" :style="{ width: limitProgress + '%' }"></div>
                            </div>
                            <p class="text-[9px] text-wallet-textSecondary">Kunlik limit har kuni Toshkent vaqti bilan 00:00 da yangilanadi.</p>
                        </div>

                        <!-- Counters -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="wallet-card p-4 text-center">
                                <span class="text-[9px] font-bold text-wallet-textSecondary uppercase tracking-wider">Joylashtirilgan</span>
                                <p class="text-xl font-extrabold text-white mt-1">{{ summary.published_posts }} ta</p>
                            </div>
                            <div class="wallet-card p-4 text-center">
                                <span class="text-[9px] font-bold text-wallet-textSecondary uppercase tracking-wider">Rejalashtirilgan</span>
                                <p class="text-xl font-extrabold text-white mt-1">{{ summary.scheduled_posts }} ta</p>
                            </div>
                        </div>

                        <!-- Chart container -->
                        <div class="wallet-card p-4">
                            <h3 class="text-[10px] font-bold text-wallet-textSecondary uppercase tracking-wider mb-3.5">So'nggi 7 kunlik AI ishlatilishi</h3>
                            <canvas id="usageChart" height="200"></canvas>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 6. OPERATORS VIEW -->
                    <!-- ============================================== -->
                    <div v-if="activeTab === 'operators'">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-white">💼 Operatorlar rollari</h2>
                            <button @click="activeTab = 'home'" class="text-xs text-wallet-blue font-semibold hover:text-white transition">Orqaga</button>
                        </div>

                        <div v-if="summary.plan !== 'business'" class="wallet-card p-6 text-center space-y-4">
                            <div class="text-4xl text-center">🔒</div>
                            <h3 class="font-bold text-sm text-white">Biznes Tarif Kerak</h3>
                            <p class="text-xs text-wallet-textSecondary leading-relaxed">Ko'p operatorli boshqaruv va kanallarga alohida rollar biriktirish Biznes obunachilar uchun ochiq.</p>
                            <button @click="triggerPremium" class="px-4 py-2.5 bg-wallet-blue hover:bg-blue-600 text-white font-bold rounded-xl text-xs transition duration-200 btn-active">
                                💎 Upgrade qilish
                            </button>
                        </div>

                        <div v-else class="wallet-card p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xs font-bold text-wallet-textSecondary uppercase tracking-wider">Operatorlar ro'yxati</h3>
                                <button class="text-xs font-bold text-wallet-blue hover:text-white transition">➕ Qo'shish</button>
                            </div>
                            <div class="divide-y divide-white/5">
                                <div v-for="op in operators" :key="op.id" class="py-3.5 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-white">{{ op.name }}</p>
                                        <p class="text-xs text-wallet-textSecondary">@{{ op.username }}</p>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-wallet-blue/10 text-wallet-blue border border-wallet-blue/20">
                                        {{ op.role.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Bottom Floating Capsule Navigation Bar (Wallet Style) -->
            <nav class="fixed bottom-6 left-4 right-4 bg-[#1a222d] border border-white/5 z-50 flex items-center justify-between px-3 py-2 rounded-2xl shadow-2xl">
                <button @click="switchTab('home')" class="flex-1 flex flex-col items-center py-1 rounded-xl transition duration-200 btn-active" :class="activeTab === 'home' ? 'text-wallet-blue' : 'text-wallet-textSecondary'">
                    <div class="p-1 px-4 rounded-lg" :class="activeTab === 'home' ? 'bg-wallet-pillActive text-white' : ''">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>
                    <span class="text-[9px] font-bold mt-1">Hamyon</span>
                </button>
                <button @click="switchTab('calendar')" class="flex-1 flex flex-col items-center py-1 rounded-xl transition duration-200 btn-active" :class="activeTab === 'calendar' || activeTab === 'edit-post' ? 'text-wallet-blue' : 'text-wallet-textSecondary'">
                    <div class="p-1 px-4 rounded-lg" :class="activeTab === 'calendar' || activeTab === 'edit-post' ? 'bg-wallet-pillActive text-white' : ''">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <span class="text-[9px] font-bold mt-1">Taqvim</span>
                </button>
                <button @click="switchTab('channels')" class="flex-1 flex flex-col items-center py-1 rounded-xl transition duration-200 btn-active" :class="activeTab === 'channels' ? 'text-wallet-blue' : 'text-wallet-textSecondary'">
                    <div class="p-1 px-4 rounded-lg" :class="activeTab === 'channels' ? 'bg-wallet-pillActive text-white' : ''">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path d="M11 5L6 9H2v6h4l5 4V5z"></path>
                            <path d="M19 6c0 0-3 2.7-3 6s3 6 3 6"></path>
                        </svg>
                    </div>
                    <span class="text-[9px] font-bold mt-1">Kanallar</span>
                </button>
                <button @click="switchTab('operators')" class="flex-1 flex flex-col items-center py-1 rounded-xl transition duration-200 btn-active" :class="activeTab === 'operators' ? 'text-wallet-blue' : 'text-wallet-textSecondary'">
                    <div class="p-1 px-4 rounded-lg" :class="activeTab === 'operators' ? 'bg-wallet-pillActive text-white' : ''">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <span class="text-[9px] font-bold mt-1">Biznes</span>
                </button>
            </nav>
        </div>
    `,
    setup() {
        const loading = ref(true);
        const activeTab = ref('home');
        const posts = ref([]);
        const channels = ref([]);
        const operators = ref([]);
        const error = ref(null);
        const message = ref(null);
        const showBanner = ref(true);

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

        // Compute initials of active user from WebApp
        const userInitials = computed(() => {
            const user = window.Telegram?.WebApp?.initDataUnsafe?.user;
            if (user?.first_name) {
                return user.first_name.charAt(0).toUpperCase();
            }
            return 'U';
        });

        // Computed Progress Percentage
        const limitProgress = computed(() => {
            const limit = summary.value.daily_limit || 5;
            const used = summary.value.daily_used || 0;
            return Math.min(Math.round((used / limit) * 100), 100);
        });

        // Telegram WebApp raw initData
        const tgInitData = window.Telegram?.WebApp?.initData || '';

        // Extract query parameters for Reply Keyboard WebApp fallback signature
        const urlParams = new URLSearchParams(window.location.search);
        const urlTgId = urlParams.get('tg_id') || '';
        const urlHash = urlParams.get('hash') || '';

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
                'draft': 'bg-white/[0.08] text-wallet-textSecondary border border-white/5',
                'scheduled': 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                'posted': 'bg-wallet-green/10 text-wallet-green border border-wallet-green/20',
                'failed': 'bg-wallet-red/10 text-wallet-red border border-wallet-red/20'
            }[status] || 'bg-white/[0.08] text-wallet-textSecondary';
        };

        // HTTP request wrapper with secure headers
        const apiRequest = async (url, method = 'GET', data = null) => {
            // Append signature query parameters if Telegram initData is missing (e.g. from Reply Keyboard WebApp)
            if (!tgInitData && urlTgId && urlHash) {
                const separator = url.includes('?') ? '&' : '?';
                url = `${url}${separator}tg_id=${urlTgId}&hash=${urlHash}`;
            }

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

                // Load chart data if stats tab or home tab loaded
                setTimeout(() => {
                    renderChart(statsRes.charts.ai_usage);
                }, 150);

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
                        label: 'AI So\'rovlari',
                        data: requests.length ? requests : [0],
                        backgroundColor: '#2f87f5',
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
                            grid: { color: 'rgba(255, 255, 255, 0.04)' },
                            ticks: { color: '#8093a8', font: { size: 9 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#8093a8', font: { size: 9 } }
                        }
                    }
                }
            });
        };

        const switchTab = (tab) => {
            activeTab.value = tab;
            message.value = null;
            error.value = null;
            loadData();
        };

        const openChannelSettings = (channel) => {
            activeTab.value = 'channels';
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

        // Show premium alert / modal popup inside WebApp
        const triggerPremium = () => {
            window.Telegram?.WebApp?.showPopup({
                title: "Premium Obuna",
                message: "Ushbu tariflarni ochish va to'lovni tasdiqlash uchun bot menyusidagi 'Premium Tarif' bo'limidan foydalaning.",
                buttons: [{ id: "ok", type: "default", text: "Tushunarli" }]
            });
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
            showBanner,
            userInitials,
            limitProgress,
            formatDate,
            getStatusLabel,
            getStatusClass,
            switchTab,
            openChannelSettings,
            openEditPost,
            savePost,
            saveChannel,
            triggerPremium
        };
    }
};

createApp(App).mount('#app');

