# AmneziaWG 2.0 для OPNsense

Плагин управляет туннелями **AmneziaWG 2.0** на OPNsense: несколько независимых подключений (инстансов), веб-интерфейс, импорт клиентских `.conf`, генерация ключей и конфигов для `awg-quick`.

Форк/ветка на базе [antspopov/opnsense_amnezia_plugin](https://github.com/antspopov/opnsense_amnezia_plugin), доработанная под протокол **2.0** (параметры как у [bivlked/amneziawg-installer](https://github.com/bivlked/amneziawg-installer)).

---

## Совместимость

| Версия | Поддержка |
|--------|-----------|
| **AmneziaWG 2.0** | Да — основной сценарий |
| **AmneziaWG 1.x** | Нет — другой набор полей и поведение пиров |

Клиенты: Amnezia VPN ≥ 4.8.12.7, AmneziaWG для Windows ≥ 2.0.0, либо конфиги, сгенерированные установщиком выше. Подробнее: [ADVANCED.en.md — client compatibility](https://github.com/bivlked/amneziawg-installer/blob/main/ADVANCED.en.md).

---

## Требования

На шлюзе должны быть установлены **`amnezia-kmod`** и **`amnezia-tools`** той же линии, что и AmneziaWG 2.0 (утилиты `awg`, `awg-quick`, модуль ядра с тем же протоколом). Плагин только собирает XML → `.conf`; совместимость с сервером определяется версией стека на OPNsense.

Зависимости пакета плагина задаются в `Makefile` (`PLUGIN_DEPENDS`).

---

## Возможности

- Несколько инстансов (`awg0`, `awg1`, …), на инстанс — один peer (как в типичной схеме AmneziaWG).
- Сетка настроек, редактирование, включение/выключение.
- Генерация ключевой пары через `awg genkey` / `awg pubkey`.
- Импорт текста или файла `.conf` (в т.ч. экспорт с VPS после `amneziawg-installer`).
- Статус и трафик через интеграцию с `awg show`.
- Управление сервисом через `configd` (старт / стоп / reconfigure).

---

## Параметры AmneziaWG 2.0 в секции `[Interface]`

Плагин хранит и записывает в `/usr/local/etc/amneziawg/awgX.conf` те же ключи, что и клиент/сервер установщика:

| Параметр | Описание |
|----------|----------|
| `Jc`, `Jmin`, `Jmax` | Обфускация (junk) |
| `S1` … `S4` | Отступы сообщений (2.0 добавляет `S3`, `S4`) |
| `H1` … `H4` | Идентификаторы/диапазоны; допускается формат **одного числа** или **`min-max`** (как в выдаче установщика) |
| `I1` | Опционально, CPS / concealment — длинная строка из `awgsetup_cfg.init` или клиентского `.conf` |

Строки **`UserLand`** (эпоха 1.x) не используются и при импорте игнорируются. Пустые **`I2` … `I5`** в импортируемом файле можно не переносить — генератор пишет только нужные для 2.0 поля.

**Клиентский** профиль часто без `ListenPort` — это нормально: при пустом порте в модели строка `ListenPort` в `.conf` не добавляется.

---

## Установка

1. Установить зависимости ядра и пользовательских утилит AmneziaWG 2.0 (порты/пакеты под вашу версию OPNsense).
2. Собрать и установить плагин (см. раздел [Сборка](#сборка)) или поставить готовый пакет, если он есть в вашей среде.
3. В веб-интерфейсе: **VPN → AmneziaWG** — включить сервис в общих настройках, создать инстанс или импортировать `.conf`.
4. Применить конфигурацию (**Apply** / перезапуск сервиса AmneziaWG).

---

## Использование

### Инстансы

**VPN → AmneziaWG → Instances** — добавление и правка: ключи, адрес туннеля, DNS, endpoint пира, `AllowedIPs`, параметры 2.0 (`Jc` … `I1`). Для полей **H1–H4** допустимы и число, и диапазон `мин-макс`.

### Импорт

**VPN → AmneziaWG → Import** — вставить текст или выбрать `.conf`, затем **Import**. Данные подставляются в форму нового инстанса; проверьте имя, номер инстанса и при необходимости поправьте поля перед сохранением.

### Статус

**VPN → AmneziaWG → Status** — сводка по инстансам (через скрипты статуса плагина).

---

## Файлы на системе

| Назначение | Путь |
|------------|------|
| Конфиг инстанса | `/usr/local/etc/amneziawg/awg0.conf`, `awg1.conf`, … |
| Сервисный скрипт | `/usr/local/opnsense/scripts/AmneziaWG/amneziawg-service-control.php` |
| Логи (типично) | `/var/log/system.log` (фильтр по AmneziaWG / awg) |

Имена интерфейсов: `awg0`, `awg1`, … (см. `InstanceField.php`).

---

## CLI (`configctl`)

```bash
configctl amneziawg start
configctl amneziawg stop
configctl amneziawg restart
configctl amneziawg reconfigure              # все инстансы
configctl amneziawg reconfigure <uuid>       # только указанный инстанс
configctl amneziawg status
configctl amneziawg show                     # подробный вывод (awg_show.py)
configctl amneziawg gen_keypair
configctl amneziawg remove_instance <uuid>   # снять туннель и удалить awgX.conf
```

Точный набор действий см. в `src/opnsense/service/conf/actions.d/actions_amneziawg.conf`.

---

## Устранение неполадок

- **Не поднимается интерфейс** — проверьте, что AmneziaWG включён в общих настройках плагина; что `awg-quick` и модуль ядра соответствуют 2.0; `awg show` с консоли.
- **Импорт не парсится** — в `[Interface]` должны быть `PrivateKey`, в `[Peer]` — `PublicKey`; для клиента с сервера скопируйте полный блок параметров 2.0.
- **Несовпадение с сервером** — все obfuscation-параметры (`Jc` … `I1`) должны совпадать с сервером; endpoint и ключи — как в рабочем клиенте.

---

## Сборка

В дереве исходников OPNsense (каталог плагинов):

```bash
cd plugins/security/amneziawg
make package
```

Локально в клоне этого репозитория структура та же: `Makefile`, `src/opnsense/...` — переносите каталог плагина в дерево `opnsense/plugins` согласно [документации сборки OPNsense](https://docs.opnsense.org/development/examples/helloworld.html).

Зависимости для разработки: см. комментарии в `Makefile` (`amnezia-tools`, `amnezia-kmod` из портов).

---

## Лицензия

Как у OPNsense / исходного плагина — см. файлы в репозитории upstream.

---

## Ссылки

- [AmneziaWG (upstream)](https://github.com/amnezia/amneziawg)
- [Установщик сервера AWG 2.0 (bivlked)](https://github.com/bivlked/amneziawg-installer)
- [Исходный плагин OPNsense](https://github.com/antspopov/opnsense_amnezia_plugin)
