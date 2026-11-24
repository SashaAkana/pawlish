<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Генератор файлов отчёта</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Выберите период и формат. Файл сформируется по данным из базы и сразу скачается без сохранения на сервере.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Период</label>
                        <div class="relative mt-1">
                            <select
                                wire:model.live="period"
                                class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            >
                                <option value="today">Сегодня</option>
                                <option value="week">Текущая неделя</option>
                                <option value="month">Текущий месяц</option>
                                <option value="custom">Произвольный период</option>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    @if($period === 'custom')
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Дата от</label>
                            <input
                                type="date"
                                wire:model.live="dateFrom"
                                class="filament-forms-input block w-full mt-1 rounded-xl border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Дата до</label>
                            <input
                                type="date"
                                wire:model.live="dateTo"
                                class="filament-forms-input block w-full mt-1 rounded-xl border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                            >
                        </div>
                    @endif

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Формат файла</label>
                        <div class="relative mt-1">
                            <select
                                wire:model.live="format"
                                class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            >
                                <option value="csv">CSV (для импорта / переноса)</option>
                                <option value="pdf">PDF (для печати и отправки)</option>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    @if($this->getDownloadUrl())
                        <a
                            href="{{ $this->getDownloadUrl() }}"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium transition"
                        >
                            Скачать {{ strtoupper($format) }}
                        </a>
                    @else
                        <button
                            type="button"
                            disabled
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-gray-300 dark:bg-gray-700 text-white text-sm font-medium cursor-not-allowed"
                        >
                            Укажите даты для произвольного периода
                        </button>
                    @endif

                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        В выгрузке: сводка, доходы (для «Мой налог») и расходы.
                    </span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
