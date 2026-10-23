import json, base64, sys
logo = base64.b64encode(open('/root/work/anahnuclub/public/images/logo-mark.png','rb').read()).decode()
L = {
 'ru': dict(rtl=False, slogan='Лучшая соцсеть без цензуры', b=['Пишите на своём языке, всё переводится само','Сообщения, файлы и видеозвонки','Группы, фото, видео и события'], cta='Присоединяйтесь бесплатно',
   inc='שלום! מה שלומך?', tr='Привет! Как дела?', trl='Переведено с иврита', out='Отлично! Рад тебя видеть 🙂', chat='Сообщения', online='в сети', post='Пикник на набережной в субботу, кто с нами?', like='Нравится', com='Комментировать'),
 'en': dict(rtl=False, slogan='The best social network without censorship', b=['Write in your language, everything is translated','Messages, files and video calls','Groups, photos, videos and events'], cta='Join for free',
   inc='Привет! Как дела?', tr='Hi! How are you?', trl='Translated from Russian', out='Great! Good to see you 🙂', chat='Messages', online='online', post='Picnic on the promenade on Saturday, who is in?', like='Like', com='Comment'),
 'de': dict(rtl=False, slogan='Das beste soziale Netzwerk ohne Zensur', b=['Schreiben Sie in Ihrer Sprache, alles wird übersetzt','Nachrichten, Dateien und Videoanrufe','Gruppen, Fotos, Videos und Events'], cta='Kostenlos beitreten',
   inc='Привет! Как дела?', tr='Hallo! Wie geht es dir?', trl='Aus dem Russischen übersetzt', out='Super! Schön dich zu sehen 🙂', chat='Nachrichten', online='online', post='Picknick an der Promenade am Samstag, wer kommt mit?', like='Gefällt mir', com='Kommentieren'),
 'he': dict(rtl=True, slogan='הרשת החברתית הטובה ביותר, ללא צנזורה', b=['כתבו בשפה שלכם, הכול מתורגם אוטומטית','הודעות, קבצים ושיחות וידאו','קבוצות, תמונות, סרטונים ואירועים'], cta='הצטרפו בחינם',
   inc='Привет! Как дела?', tr='שלום! מה שלומך?', trl='תורגם מרוסית', out='מצוין! שמח לראות אותך 🙂', chat='הודעות', online='מחובר', post='פיקניק על הטיילת בשבת, מי מצטרף?', like='אהבתי', com='תגובה'),
 'ar': dict(rtl=True, slogan='أفضل شبكة اجتماعية بلا رقابة', b=['اكتب بلغتك، وكل شيء يُترجم تلقائيًا','رسائل وملفات ومكالمات فيديو','مجموعات وصور وفيديوهات وفعاليات'], cta='انضم مجانًا',
   inc='Привет! Как дела?', tr='مرحبًا! كيف حالك؟', trl='مترجم من الروسية', out='ممتاز! سعيد برؤيتك 🙂', chat='الرسائل', online='متصل', post='نزهة على الكورنيش يوم السبت، من سيأتي؟', like='أعجبني', com='تعليق'),
 'tg': dict(rtl=False, slogan='Беҳтарин шабакаи иҷтимоӣ бе сензура', b=['Бо забони худ нависед, ҳама тарҷума мешавад','Паёмҳо, файлҳо ва зангҳои видеоӣ','Гурӯҳҳо, аксҳо, видеоҳо ва чорабиниҳо'], cta='Ройгон ҳамроҳ шавед',
   inc='Привет! Как дела?', tr='Салом! Корҳо чӣ хел?', trl='Аз забони русӣ тарҷума шуд', out='Олӣ! Аз дидани ту шодам 🙂', chat='Паёмҳо', online='дар шабака', post='Пикник дар соҳил рӯзи шанбе, кӣ бо мо?', like='Писанд', com='Шарҳ'),
}
for code, s in L.items():
    d = 'rtl' if s['rtl'] else 'ltr'
    bullets = ''.join(f'<li><span class="tick">✓</span>{x}</li>' for x in s['b'])
    html = f'''<!doctype html><html dir="{d}"><head><meta charset="utf-8">
<link href="https://fonts.bunny.net/css?family=rubik:400,500,600,700|noto-sans-arabic:400,500,600,700" rel="stylesheet">
<style>
*{{box-sizing:border-box;margin:0}} body{{width:1200px;height:630px;overflow:hidden;font-family:Rubik,'Noto Sans Arabic',sans-serif;
background:linear-gradient(135deg,#0e6f88 0%,#2b5fb8 55%,#6d4ad8 100%);color:#fff;position:relative}}
.glow{{position:absolute;border-radius:50%;filter:blur(60px);opacity:.35}}
.left{{position:absolute;top:0;bottom:0;{'right' if s['rtl'] else 'left'}:0;width:600px;padding:64px 56px;display:flex;flex-direction:column}}
.brand{{display:flex;align-items:center;gap:16px;font-size:34px;font-weight:700;letter-spacing:.3px}}
.brand img{{width:64px;height:64px;border-radius:16px;background:#fff;padding:6px}}
h1{{font-size:{'46' if s['rtl'] or len(s['slogan']) > 30 else '54'}px;line-height:1.08;font-weight:700;margin-top:40px}}
ul{{list-style:none;padding:0;margin-top:30px;font-size:23px;line-height:1.5}} li{{display:flex;gap:12px;align-items:baseline;margin:6px 0;opacity:.95}}
.tick{{display:inline-flex;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.2);align-items:center;justify-content:center;font-size:16px;flex:none}}
.cta{{margin-top:auto;align-self:flex-start;background:#fff;color:#0e6f88;font-weight:700;font-size:24px;padding:16px 30px;border-radius:999px;box-shadow:0 10px 30px rgba(0,0,0,.2)}}
.stage{{position:absolute;top:0;bottom:0;{'left' if s['rtl'] else 'right'}:0;width:600px}}
.card{{position:absolute;background:#fff;color:#0f172a;border-radius:22px;box-shadow:0 30px 60px rgba(10,20,60,.35)}}
.post{{width:360px;top:70px;{'left' if s['rtl'] else 'right'}:200px;padding:16px;transform:rotate({'4' if s['rtl'] else '-4'}deg)}}
.who{{display:flex;gap:10px;align-items:center;font-weight:600;font-size:16px}} .who small{{display:block;color:#64748b;font-weight:400;font-size:12px}}
.av{{width:42px;height:42px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px}}
.pic{{height:190px;border-radius:14px;margin:12px 0;background:
radial-gradient(circle at 75% 28%,#ffe08a 0 34px,transparent 35px),
linear-gradient(180deg,#7cc6f2 0%,#bfe6fb 52%,#2a9dbf 52%,#1d7fa3 70%,#f3d7a1 70%,#e9c487 100%);position:relative;overflow:hidden}}
.pic:after{{content:'';position:absolute;left:-20px;right:-20px;bottom:30%;height:40px;background:radial-gradient(ellipse at 30% 100%,#2e8b57 0 60px,transparent 61px),radial-gradient(ellipse at 80% 100%,#3aa36a 0 80px,transparent 81px)}}
.ptext{{font-size:15px;line-height:1.35}} .acts{{display:flex;gap:18px;color:#64748b;font-size:13px;margin-top:10px;border-top:1px solid #e2e8f0;padding-top:10px}}
.chat{{width:330px;top:200px;{'left' if s['rtl'] else 'right'}:40px;overflow:hidden;transform:rotate({'-3' if s['rtl'] else '3'}deg)}}
.chead{{display:flex;gap:10px;align-items:center;padding:14px 16px;border-bottom:1px solid #e2e8f0;font-weight:600}} .chead small{{display:block;color:#16a34a;font-weight:500;font-size:12px}}
.dot{{position:relative}} .dot:after{{content:'';position:absolute;bottom:0;{'left' if s['rtl'] else 'right'}:0;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff}}
.msgs{{padding:16px;display:flex;flex-direction:column;gap:10px;background:#f8fafc}}
.b{{max-width:85%;padding:10px 14px;border-radius:18px;font-size:15px;line-height:1.35}}
.in{{background:#fff;border:1px solid #e2e8f0;align-self:flex-start}} .in small{{display:block;color:#94a3b8;font-size:11px;margin-top:4px}} .orig{{color:#94a3b8;font-size:12px;margin-bottom:3px}}
.out{{background:#117892;color:#fff;align-self:flex-end}}
.typing{{display:flex;gap:6px;align-items:center;padding:12px 16px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:14px}}
</style></head><body>
<div class="glow" style="width:420px;height:420px;background:#7dd3fc;top:-120px;{'right' if s['rtl'] else 'left'}:-80px"></div>
<div class="glow" style="width:380px;height:380px;background:#c4b5fd;bottom:-140px;{'left' if s['rtl'] else 'right'}:60px"></div>
<div class="left"><div class="brand"><img src="data:image/png;base64,{logo}">anahnu.club</div>
<h1>{s['slogan']}</h1><ul>{bullets}</ul><div class="cta">{s['cta']}</div></div>
<div class="stage">
 <div class="card post"><div class="who"><div class="av" style="background:#f59e0b">МК</div><div dir="ltr">Maya K.<small>12 min</small></div></div>
  <div class="pic"></div><div class="ptext" dir="auto">{s['post']}</div><div class="acts"><span>👍 {s['like']} · 48</span><span>💬 {s['com']} · 12</span></div></div>
 <div class="card chat"><div class="chead"><div class="av dot" style="background:#8b5cf6">DL</div><div><span dir="ltr">David L.</span><small>● {s['online']}</small></div></div>
  <div class="msgs"><div class="b in"><div class="orig" dir="auto">{s['inc']}</div><div dir="auto">{s['tr']}</div><small>🌐 {s['trl']}</small></div>
  <div class="b out" dir="auto">{s['out']}</div></div><div class="typing">📎 &nbsp; 🎥 &nbsp; …</div></div>
</div></body></html>'''
    open(f'/root/scripts/og/{code}.html','w').write(html)
print('ok')
