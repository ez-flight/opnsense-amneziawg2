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

## Инструкция по установке (по шагам)

Ниже — установка на OPNsense по SSH от **root**. Версии пакетов с `pkg.freebsd.org` периодически обновляются: при ошибке `404` откройте каталог [FreeBSD:14:amd64/latest/All](https://pkg.freebsd.org/FreeBSD:14:amd64/latest/All/) и подберите актуальные имена файлов.

### Шаг 1. Установка зависимостей AmneziaWG 2.0

Подключитесь к OPNsense по SSH и выполните команды от пользователя **root**.

#### 1.1. Очистите проблемные репозитории (если добавляли ранее)

```bash
rm -f /usr/local/etc/pkg/repos/FreeBSD.conf 2>/dev/null
rm -f /usr/local/etc/pkg/repos/*.conf
pkg update -f
pkg clean -a
```

#### 1.2. Скачайте и установите `amnezia-kmod` и `amnezia-tools` напрямую (версия 2.0)

Пример для FreeBSD 14 amd64 (при необходимости замените имена `.pkg` на актуальные):

```bash
# amnezia-kmod (модуль ядра)
fetch -o /tmp/amnezia-kmod.pkg https://pkg.freebsd.org/FreeBSD:14:amd64/latest/All/amnezia-kmod-2.0.10.1403000.pkg
# amnezia-tools (утилиты awg)
fetch -o /tmp/amnezia-tools.pkg https://pkg.freebsd.org/FreeBSD:14:amd64/latest/All/amnezia-tools-1.0.20250903.pkg
```

Если ссылки не работают, зайдите на https://pkg.freebsd.org/FreeBSD:14:amd64/latest/All/ и найдите свежие версии **amnezia-kmod** (должна быть **2.0.x**) и **amnezia-tools** (не ниже **1.0.2025**). Замените имена файлов в командах `fetch`.

```bash
pkg add /tmp/amnezia-kmod.pkg /tmp/amnezia-tools.pkg
```

#### 1.3. Загрузите модуль ядра и добавьте автозагрузку

```bash
kldload if_amn
echo 'if_amn_load="YES"' >> /boot/loader.conf.local
```

#### 1.4. Проверьте, что утилиты работают

```bash
awg --version
awg-quick --version
```

Если команды не найдены, перезагрузите роутер (`reboot`) и повторите проверку.

### Шаг 2. Установка плагина opnsense-amneziawg2

Плагин не поставляется готовым пакетом в стандартных репозиториях OPNsense; его можно **собрать из исходников на роутере** (или на другой машине с совместимой FreeBSD — см. раздел **«Альтернативный способ установки»** ниже).

#### 2.1. Установите инструменты для сборки

```bash
pkg install git
```

Сборка плагина идёт **встроенным в систему BSD make** (`/usr/bin/make`). Пакет **gmake** (GNU make) не нужен и **нельзя** вызывать `gmake package` — в `Makefile` используется директива `.include`, она есть только у BSD make (ошибка `missing separator` на строке с `.include`).

Если `pkg` выдаёт segmentation fault, используйте `pkg-static install git`.

#### 2.2–2.3. Клон дерева `plugins` и установка исходников плагина на место

Сборка **из одного только** `/tmp/opnsense-amneziawg2` **невозможна**: нужен репозиторий **[opnsense/plugins](https://github.com/opnsense/plugins)** — в нём лежит каталог **`Mk/`** с `plugins.mk`. Путь `/path/to/plugins/security/amneziawg` в документации — это **пример**, не команда для вставки в shell.

Скопируйте блок целиком (пути реальные, `/tmp` можно заменить на другой каталог):

```bash
cd /tmp
rm -rf plugins opnsense-amneziawg2
git clone --depth 1 https://github.com/opnsense/plugins.git
git clone https://github.com/ez-flight/opnsense-amneziawg2.git
rm -rf plugins/security/amneziawg
mkdir -p plugins/security
cp -R opnsense-amneziawg2 plugins/security/amneziawg
cd plugins/security/amneziawg
make package
```

Используйте **`make`** (BSD make), не **`gmake`**.

Проверка: из каталога `amneziawg` файл `../../Mk/plugins.mk` должен существовать (`ls ../../Mk/plugins.mk`).

После успешной сборки готовый **`.pkg` лежит не в корне плагина**, а в подкаталоге **`work/pkg/`** (так устроен [Mk/plugins.mk](https://github.com/opnsense/plugins/blob/master/Mk/defaults.mk) OPNsense: `PKGDIR=work/pkg`). Имя задаётся при сборке, часто **`os-amneziawg-devel-1.1.pkg`** (суффикс `-devel` у tier development).

#### 2.4. Установите собранный пакет

Из каталога плагина (где запускали `make package`):

```bash
ls -la work/pkg/
pkg add work/pkg/os-amneziawg-devel-1.1.pkg
```

Подставьте точное имя из `ls work/pkg/`. Короткий вариант:

```bash
pkg add work/pkg/os-amneziawg*.pkg
```

Если `ls *.pkg` в корне плагина пишет **No match** — это нормально: ищите пакет только в **`work/pkg/`**. Установка без расширения `.pkg` (`pkg add os-amneziawg-devel-1.1`) не сработает — нужен путь к файлу.

#### 2.5. Перезапустите `configd` и очистите кэш меню веб-интерфейса

```bash
service configd restart
rm -f /var/lib/php/tmp/opnsense_menu_cache.xml
```

Обновите страницу браузера (**Ctrl+F5**). В меню **VPN** должен появиться пункт **AmneziaWG** (без цифры «2» в названии).

### Шаг 3. Настройка плагина через веб-интерфейс

#### 3.1. Общие настройки

**VPN → AmneziaWG → General** — включите **Enable AmneziaWG**.

(Опционально) включите **Watchdog**, если такая опция есть в вашей сборке плагина, для автоматического перезапуска.

Нажмите **Save**.

#### 3.2. Импорт конфигурации клиента (рекомендуемый способ)

1. Перейдите в **VPN → AmneziaWG → Import**.
2. Вставьте содержимое `.conf` (сгенерированного сервером AmneziaWG 2.0) или загрузите файл.
3. Нажмите **Import**.
4. Откроется форма создания инстанса. Проверьте поля, особенно:
   - **H1…H4** — могут быть числом или диапазоном (например `1571435548-1821290639`);
   - **I1** — длинная строка (если есть в конфиге);
   - **S3**, **S4** — должны соответствовать серверу.
5. **Listen Port**: для клиентского профиля часто можно оставить пустым — тогда `ListenPort` не попадёт в `.conf`; если нужен входящий порт — укажите его.
6. Нажмите **Save**.

#### 3.3. Ручное создание инстанса

**VPN → AmneziaWG → Instances → Add** — заполните адрес интерфейса (**Address**), **PrivateKey** (можно сгенерировать), параметры обфускации (**Jc**, **Jmin**, **Jmax**, **S1…S4**, **H1…H4**, **I1**), данные пира (**PublicKey**, **Endpoint**, **AllowedIPs**). Сохраните.

#### 3.4. Применение настроек

После сохранения нажмите **Apply** вверху страницы (или дождитесь применения). Плагин создаст, например, `/usr/local/etc/amneziawg/awg0.conf` и поднимет интерфейс `awg0`.

### Шаг 4. Настройка интерфейса, шлюза и правил файрвола

После запуска туннеля интерфейс **awg0** появляется в системе — его нужно оформить в OPNsense.

#### 4.1. Назначьте интерфейс

**Interfaces → Assignments** — в списке новых интерфейсов найдите **awg0**, нажмите **+**.

Откройте новый интерфейс (например **OPT1**):

- **Enable** — включите;
- **IPv4 Configuration Type** — **Static IPv4**;
- **IPv4 address** — туннельный адрес из конфига (часто **/32**, например `10.8.1.3/32`);
- **Prevent interface removal** — включите.

Сохраните.

#### 4.2. Шлюз (при необходимости маршрутизации через туннель)

**System → Gateways → Configuration → Add**:

- **Interface** — созданный интерфейс (например **OPT1**);
- **Name** — например `AWG_GW`;
- **Gateway IP** — по ситуации (для типичного P2P с **AllowedIPs** шлюз может не понадобиться);
- **Far Gateway** — включите;
- **Disable Gateway Monitoring** — включите (чтобы шлюз не помечался недоступным без ICMP).

Сохраните.

#### 4.3. Нормализация MSS (рекомендуется для AmneziaWG)

**Firewall → Settings → Normalization → Add**:

- **Interface** — интерфейс туннеля (**awg0** / назначенный **OPT*);
- **Protocol** — **TCP**;
- **Max MSS** — **1380** (или **1300** при проблемах).

Сохраните и примените.

#### 4.4. Outbound NAT (если трафик не уходит в туннель)

**Firewall → NAT → Outbound** — режим **Hybrid**, добавьте правило:

- **Interface**: интерфейс туннеля;
- **Source**: например **LAN net**;
- **Destination**: **any**;
- **Translation**: **Interface address**.

Сохраните и примените.

#### 4.5. Правила LAN

**Firewall → Rules → LAN → Add** — разрешите нужный трафик; при использовании шлюза **AWG_GW** укажите его в поле **Gateway**. Правило размещайте выше «Default allow LAN to any», если так задумана политика.

### Шаг 5. Проверка работоспособности

**VPN → AmneziaWG → Status** — статус и трафик по инстансам.

В консоли:

```bash
awg show
ifconfig awg0
ping -c 4 10.8.1.1   # если сервер отвечает на туннельном IP
```

Проверка выхода в интернет через туннель:

```bash
curl --interface awg0 ifconfig.me
```

(ожидается публичный IP стороны VPN-сервера).

### Устранение возможных проблем

| Симптом | Что сделать |
|--------|-------------|
| Интерфейс **awg0** не создаётся | `kldstat`, затем фильтр `grep if_amn`; логи: `tail -f /var/log/system.log` и поиск по «Amnezia»; вручную: `configctl amneziawg start` и смотреть ошибки. |
| **Segmentation fault** у `pkg` | Использовать `pkg-static` или ставить пакеты через `pkg add <файл.pkg>` (как в шаге 1.2). |
| Импорт не видит параметры 2.0 | Убедитесь, что используется этот репозиторий (**ez-flight**), в `.conf` есть **[Interface]** / **[Peer]** и полный набор **S3**, **S4**; при отсутствии полей плагин подставит дефолты — они могут не совпасть с сервером. |
| **H1…H4** «неправильный формат» | В плагине допустимы одно число или диапазон **без пробелов** (`мин-макс`). Копируйте значение из рабочего `.conf` как есть. |
| Нет интернета через туннель | MSS (п. 4.3), **AllowedIPs**, маршрутизация/шлюз, **Outbound NAT** на **awg0**. |
| **`pkg add` → No match** | Готовый `.pkg` в каталоге **`work/pkg/`**, не в корне плагина. См. `ls work/pkg/` и `pkg add work/pkg/os-amneziawg*.pkg`. Имя часто **`os-amneziawg-devel-1.1.pkg`**. |

### Альтернативный способ установки

Если `make package` на роутере завершается ошибкой (нет `Mk/plugins.mk` или не то дерево), соберите `.pkg` на машине с **полным деревом OPNsense** и **FreeBSD**, соответствующим вашей версии OPNsense, затем скопируйте `os-amneziawg*.pkg` (часто `os-amneziawg-devel-*.pkg`) на роутер и выполните `pkg add ./имя.pkg`. Ручное копирование файлов плагина в дерево OPNsense без сборки пакета возможно, но сложнее и не рекомендуется.

### Заключение

После выполнения шагов на OPNsense должен работать **AmneziaWG 2.0** с обфускацией и импортом конфигов. При ошибках сохраните вывод команд и фрагменты логов — по ним проще локализовать проблему.

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

## Сборка для разработчиков

Сборка внутри [официального дерева плагинов OPNsense](https://docs.opnsense.org/development/examples/helloworld.html):

```bash
cd plugins/security/amneziawg   # каталог с этим плагином в дереве opnsense/plugins
make package
```

В корне клонированного репозитория тот же `Makefile` и `src/opnsense/...`; при сборке «вне дерева» OPNsense может потребоваться полное окружение **opnsense-tools** — проще собирать на самой OPNsense или в jail с установленным набором разработчика.

Зависимости: `PLUGIN_DEPENDS` в `Makefile` (`amnezia-tools`, `amnezia-kmod` должны быть уже установлены — см. **шаг 1** в разделе «Инструкция по установке» выше).

---

## Лицензия

Как у OPNsense / исходного плагина — см. файлы в репозитории upstream.

---

## Ссылки

- [AmneziaWG (upstream)](https://github.com/amnezia/amneziawg)
- [Установщик сервера AWG 2.0 (bivlked)](https://github.com/bivlked/amneziawg-installer)
- [Исходный плагин OPNsense](https://github.com/antspopov/opnsense_amnezia_plugin)
