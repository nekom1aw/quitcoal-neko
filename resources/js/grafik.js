// Grafik menu Data & Grafik: dipakai editor di CMS, daftar grafik, dan halaman publik /grafik/{id}.
// Satu grafik = spek { jenis, data: { kolom, baris }, pengaturan } seperti yang disimpan di database.
import { Chart, registerables } from "chart.js";
import { toFont, toPadding } from "chart.js/helpers";

// Palet warna yang bisa dipilih di editor; kuncinya disimpan di pengaturan.palet
const PALET = {
    quitcoal: { nama: "QuitCoal", warna: ["#16a34a", "#f59e0b", "#2563eb", "#dc2626", "#7c3aed", "#0891b2", "#db2777", "#65a30d", "#ea580c", "#475569"] },
    kontras: { nama: "Ramah buta warna", warna: ["#0072b2", "#e69f00", "#009e73", "#d55e00", "#cc79a7", "#56b4e9", "#f0e442", "#000000"] },
    laut: { nama: "Laut", warna: ["#0c4a6e", "#0284c7", "#38bdf8", "#0f766e", "#2dd4bf", "#1e3a8a", "#7dd3fc", "#134e4a"] },
    hangat: { nama: "Hangat", warna: ["#7f1d1d", "#dc2626", "#ea580c", "#f59e0b", "#facc15", "#a16207", "#fb923c", "#fde68a"] },
    batubara: { nama: "Batu bara", warna: ["#111827", "#4b5563", "#9ca3af", "#16a34a", "#f59e0b", "#d1d5db"] },
    pastel: { nama: "Pastel", warna: ["#86efac", "#93c5fd", "#fca5a5", "#fcd34d", "#c4b5fd", "#67e8f9", "#f9a8d4", "#bef264"] },
};

// Template grafik -> tipe Chart.js
const TIPE = {
    bar: "bar",
    "bar-stacked": "bar",
    "bar-100": "bar",
    "bar-horizontal": "bar",
    "bar-horizontal-stacked": "bar",
    line: "line",
    area: "line",
    "area-stacked": "line",
    pie: "pie",
    donut: "doughnut",
};

// Angka di sel: simbol di depan/belakang (Rp, %, satuan) dan spasi dibuang
const intiAngka = (nilai) => String(nilai ?? "").replace(/[\s ]/g, "").replace(/^[^\d+\-.,]+|[^\d.,]+$/g, "");

// Pemisah desimal data tabel, ditebak dari semua angka di tabel sekaligus: "koma" (1.234,5) atau
// "titik" (1,234.5). Tidak bergantung pada format tampilan, jadi mengganti format tampilan tidak
// mengubah nilainya. Tanpa petunjuk sama sekali (mis. semua angka bulat), dianggap format Indonesia.
function tebakPemisah(baris) {
    let koma = 0;
    let titik = 0;
    for (const b of baris || []) {
        for (const sel of (b || []).slice(1)) {
            const s = intiAngka(sel);
            const ik = s.lastIndexOf(",");
            const it = s.lastIndexOf(".");
            if (ik === -1 && it === -1) continue;

            const terakhir = ik > it ? "," : ".";
            const lain = terakhir === "," ? "." : ",";
            const bagian = s.split(terakhir);
            let desimal = null;
            if (s.includes(lain)) {
                desimal = terakhir; // 1.234,5 / 1,234.5: yang paling belakang desimal
            } else if (bagian.length > 2) {
                desimal = lain; // 1.234.567: pemisah ribuan
            } else if (!/^\d{3}$/.test(bagian[1])) {
                desimal = terakhir; // 12,5 / 616.25: bukan tepat tiga angka di belakang, jadi desimal
            }
            if (desimal === ",") koma++;
            if (desimal === ".") titik++;
        }
    }
    return titik > koma ? "titik" : "koma";
}

// Angka dari sel tabel dengan pemisah desimal "koma" (1.234,5) atau "titik" (1,234.5). Satu pemisah
// ribuan yang tidak diikuti tepat tiga angka tetap dibaca desimal (616.5 di data berkoma = 616,5).
function bacaAngka(nilai, pemisah = "koma") {
    if (typeof nilai === "number") return Number.isFinite(nilai) ? nilai : null;
    let s = intiAngka(nilai);
    if (!s) return null;

    const ribuan = pemisah === "titik" ? "," : ".";
    const desimal = pemisah === "titik" ? "." : ",";
    const nr = s.split(ribuan).length - 1;
    const nd = s.split(desimal).length - 1;
    if (nr && nd) {
        // Keduanya ada: pemisah yang paling belakang adalah desimal
        s = s.lastIndexOf(desimal) > s.lastIndexOf(ribuan)
            ? s.split(ribuan).join("").replace(desimal, ".")
            : s.split(desimal).join("").replace(ribuan, ".");
    } else if (nd === 1) {
        s = s.replace(desimal, ".");
    } else if (nd > 1) {
        s = s.split(desimal).join("");
    } else if (nr === 1 && !/^\d{3}$/.test(s.split(ribuan)[1])) {
        s = s.replace(ribuan, ".");
    } else {
        s = s.split(ribuan).join("");
    }

    const n = Number(s);
    return s !== "" && Number.isFinite(n) ? n : null;
}

// ringkas: untuk sumbu dan label nilai. Awalan/akhiran panjang (mis. " juta ton") membuat sumbu dan
// label penuh dan saling tumpuk, jadi hanya yang pendek (%, Rp, MW) ikut; tooltip selalu lengkap.
function formatAngka(n, p = {}, ringkas = false) {
    const desimal = p.desimal >= 0 ? p.desimal : null;
    const teks = Number(n).toLocaleString(p.formatAngka === "en" ? "en-US" : "id-ID", {
        minimumFractionDigits: desimal ?? 0,
        maximumFractionDigits: desimal ?? 2,
    });
    const awalan = p.awalan || "";
    const akhiran = p.akhiran || "";
    if (ringkas && (awalan + akhiran).trim().length > 3) return teks;
    return awalan + teks + akhiran;
}

function transparan(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

// Warna teks yang terbaca di atas warna latar tertentu: gelap di atas warna terang (mis. kuning),
// putih di atas warna gelap
function warnaTeksDi(latar) {
    if (typeof latar !== "string" || !/^#[0-9a-f]{6}$/i.test(latar)) return "#ffffff";
    const n = parseInt(latar.slice(1), 16);
    const terang = 0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255);
    return terang > 160 ? "#111827" : "#ffffff";
}

// Label angka di tiap batang/titik/potongan (pengaturan "Tampilkan label nilai")
const pluginLabelNilai = {
    id: "labelNilai",
    afterDatasetsDraw(chart, _args, opsi) {
        if (!opsi?.tampil) return;
        const { ctx } = chart;
        const lingkaran = chart.config.type === "pie" || chart.config.type === "doughnut";
        const horizontal = chart.options.indexAxis === "y";

        ctx.save();
        ctx.font = `500 11px ${Chart.defaults.font.family}`;
        // Label garis digambar paling akhir, supaya kotaknya di atas label batang
        const urutan = chart.data.datasets.map((_, i) => i).sort((a, b) =>
            (chart.getDatasetMeta(a).type === "line") - (chart.getDatasetMeta(b).type === "line"));
        urutan.forEach((i) => {
            const dataset = chart.data.datasets[i];
            const meta = chart.getDatasetMeta(i);
            if (meta.hidden) return;
            // Garis di grafik batang (grafik kombinasi): label dalam kotak berwarna garisnya
            const kotak = meta.type === "line" && chart.config.type === "bar";

            meta.data.forEach((el, j) => {
                const nilai = dataset.data[j];
                if (nilai === null || nilai === undefined || (lingkaran && !chart.getDataVisibility(j))) return;
                const teks = opsi.format(nilai);
                const latar = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[j] : dataset.backgroundColor;

                if (kotak) {
                    const lebar = ctx.measureText(teks).width + 10;
                    ctx.fillStyle = dataset.borderColor;
                    ctx.strokeStyle = "#ffffff";
                    ctx.lineWidth = 1.5;
                    ctx.beginPath();
                    ctx.roundRect(el.x - lebar / 2, el.y - 9, lebar, 18, 9);
                    ctx.fill();
                    ctx.stroke();
                    ctx.fillStyle = warnaTeksDi(dataset.borderColor);
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";
                    ctx.fillText(teks, el.x, el.y + 0.5);
                } else if (lingkaran || opsi.tumpuk) {
                    // Di dalam potongan/segmen; dilewati kalau segmennya terlalu tipis untuk teks
                    if (!lingkaran && Math.abs(horizontal ? el.x - el.base : el.base - el.y) < 14) return;
                    const titik = lingkaran ? el.tooltipPosition() : el.getCenterPoint();
                    ctx.fillStyle = warnaTeksDi(latar);
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";
                    ctx.fillText(teks, titik.x, titik.y);
                } else if (horizontal) {
                    ctx.fillStyle = "#374151";
                    ctx.textAlign = "left";
                    ctx.textBaseline = "middle";
                    ctx.fillText(teks, el.x + 4, el.y);
                } else {
                    ctx.fillStyle = "#374151";
                    ctx.textAlign = "center";
                    ctx.textBaseline = "bottom";
                    ctx.fillText(teks, el.x, el.y - 5);
                }
            });
        });
        ctx.restore();
    },
};

// Garis pemisah putih antarpotongan lingkaran/donat. Kalau digambar sebagai tepi potongan (bawaan
// Chart.js), semua garis menumpuk di titik tengah menjadi bulatan putih, makin besar makin banyak
// potongannya. Di sini tiap garis menipis ke arah pusat (seperti jari-jari roda): lebar penuh di luar,
// lalu mengecil sampai nol tepat di titik tengah, jadi garis-garisnya bertemu rapi di satu titik.
const pluginPemisahLingkaran = {
    id: "pemisahLingkaran",
    afterDatasetsDraw(chart, _args, opsi) {
        if (!opsi?.tampil) return;
        const potongan = chart.getDatasetMeta(0).data.filter((el, j) => chart.getDataVisibility(j) && el.circumference > 0);
        if (potongan.length < 2) return;

        const { ctx } = chart;
        const setengah = opsi.lebar / 2;
        ctx.save();
        ctx.fillStyle = "#ffffff";
        potongan.forEach((el, k) => {
            const sebelumnya = potongan[(k - 1 + potongan.length) % potongan.length];
            const sudut = Math.min(el.circumference, sebelumnya.circumference);
            // Jarak dari pusat tempat garis mencapai lebar penuh; di dalamnya garis mengisi paling
            // banyak seperempat celah antarpotongan, jadi tengahnya tidak memutih
            const penuh = Math.min(el.outerRadius, (opsi.lebar * 4) / sudut);
            const ux = Math.cos(el.startAngle);
            const uy = Math.sin(el.startAngle);
            const titik = (r, geser) => [el.x + ux * r - uy * geser, el.y + uy * r + ux * geser];

            ctx.beginPath();
            if (el.innerRadius > 0) {
                // Donat: lebar penuh dari lingkar dalam sampai luar
                ctx.moveTo(...titik(el.innerRadius, setengah));
                ctx.lineTo(...titik(el.outerRadius, setengah));
                ctx.lineTo(...titik(el.outerRadius, -setengah));
                ctx.lineTo(...titik(el.innerRadius, -setengah));
            } else {
                ctx.moveTo(el.x, el.y);
                ctx.lineTo(...titik(penuh, setengah));
                ctx.lineTo(...titik(el.outerRadius, setengah));
                ctx.lineTo(...titik(el.outerRadius, -setengah));
                ctx.lineTo(...titik(penuh, -setengah));
            }
            ctx.closePath();
            ctx.fill();
        });
        ctx.restore();
    },
};

// Poppins tidak punya angka subskrip (₂ di "CO₂e"), jadi browser meminjamnya dari font lain yang bentuk
// dan tebalnya tidak senada; di judul sumbu yang diputar 90° hasilnya tampak janggal. Judul sumbu yang
// memuat subskrip digambar sendiri: angkanya pakai font yang sama, diperkecil dan diturunkan.
const SUBSKRIP = "₀₁₂₃₄₅₆₇₈₉";

function tulisDenganSubskrip(ctx, teks, x, y, rotasi, font, warna) {
    const gaya = (ukuran) => [font.style, font.weight, `${ukuran}px`, font.family].filter(Boolean).join(" ");
    const kecil = Math.round(font.size * 0.72);
    const bagian = teks.split(/([₀-₉]+)/).filter(Boolean).map((t) => {
        const sub = SUBSKRIP.includes(t[0]);
        return { teks: sub ? [...t].map((c) => SUBSKRIP.indexOf(c)).join("") : t, font: gaya(sub ? kecil : font.size), turun: sub ? font.size * 0.25 : 0 };
    });

    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rotasi);
    ctx.fillStyle = warna;
    ctx.textAlign = "left";
    ctx.textBaseline = "middle";
    const lebar = bagian.map((b) => ((ctx.font = b.font), ctx.measureText(b.teks).width));
    let posisi = -lebar.reduce((a, b) => a + b, 0) / 2;
    bagian.forEach((b, i) => {
        ctx.font = b.font;
        ctx.fillText(b.teks, posisi, b.turun);
        posisi += lebar[i];
    });
    ctx.restore();
}

// Posisi sama dengan Scale.drawTitle bawaan Chart.js (judul rata tengah, sumbu kiri atau bawah)
const pluginJudulSumbu = {
    id: "judulSumbuSubskrip",
    beforeDraw(chart) {
        for (const sumbu of Object.values(chart.scales)) {
            if (sumbu.$judulSubskrip) continue;
            sumbu.$judulSubskrip = true;
            const bawaan = sumbu.drawTitle;
            sumbu.drawTitle = function () {
                const { position, title } = this.options;
                if (!title.display || typeof title.text !== "string" || !/[₀-₉]/.test(title.text) ||
                    (position !== "left" && position !== "bottom") || (title.align && title.align !== "center")) {
                    return bawaan.call(this);
                }
                const font = toFont(title.font);
                const padding = toPadding(title.padding);
                const offset = font.lineHeight / 2 + (position === "bottom" ? padding.bottom : padding.top);
                if (position === "left") {
                    tulisDenganSubskrip(this.ctx, title.text, this.left + offset, (this.top + this.bottom) / 2, -Math.PI / 2, font, title.color);
                } else {
                    tulisDenganSubskrip(this.ctx, title.text, (this.left + this.right) / 2, this.bottom - offset, 0, font, title.color);
                }
            };
        }
    },
};

// Pemisah digambar sebelum label nilai, supaya label tetap di atasnya
Chart.register(...registerables, pluginPemisahLingkaran, pluginLabelNilai, pluginJudulSumbu);
Chart.defaults.font.family = "Poppins, ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = "#4b5563";

// Pemisah desimal data: pilihan di tab Data, atau ditebak dari isi tabel ("otomatis")
function pemisahData(spek) {
    const pilihan = spek.pengaturan?.formatData;
    return pilihan === "koma" || pilihan === "titik" ? pilihan : tebakPemisah(spek.data?.baris);
}

// Konfigurasi Chart.js dari spek grafik. Kolom pertama tabel = label, kolom berikutnya = seri data.
// mini: grafik kecil di daftar, tanpa sumbu, legenda, dan interaksi.
function konfigurasi(spek, mini = false) {
    const p = spek.pengaturan || {};
    const jenis = TIPE[spek.jenis] ? spek.jenis : "bar";
    const lingkaran = jenis === "pie" || jenis === "donut";
    const garis = jenis === "line" || jenis === "area" || jenis === "area-stacked";
    const horizontal = jenis === "bar-horizontal" || jenis === "bar-horizontal-stacked";
    const tumpuk = ["bar-stacked", "bar-horizontal-stacked", "bar-100", "area-stacked"].includes(jenis);
    // Batang 100%: tiap batang = 100%, nilai seri diubah menjadi persentase dari total labelnya
    const persen = jenis === "bar-100";
    const palet = (PALET[p.palet] || PALET.quitcoal).warna;
    // Warna pilihan per seri (per potongan untuk lingkaran/donat) menimpa warna palet
    const warna = (i) => (p.warnaKhusus || [])[i] || palet[i % palet.length];
    const format = (n) => formatAngka(n, p);
    const formatRingkas = (n) => formatAngka(n, p, true);
    const formatPersen = (n) => formatAngka(n, { ...p, awalan: "", akhiran: "%", desimal: p.desimal >= 0 ? p.desimal : 0 });
    const labelNilai = !!p.labelNilai && !mini;

    const baris = (spek.data?.baris || []).filter((b) => String(b?.[0] ?? "").trim() !== "");
    const label = baris.map((b) => String(b[0]).trim());
    const pemisah = pemisahData(spek);
    let seri = (spek.data?.kolom || []).slice(1)
        .map((nama, i) => ({
            ke: i,
            nama: String(nama ?? "").trim() || `Seri ${i + 1}`,
            nilai: baris.map((b) => bacaAngka(b[i + 1], pemisah)),
        }))
        .filter((s) => s.nilai.some((v) => v !== null));
    // Lingkaran dan donat hanya memakai seri pertama
    if (lingkaran) seri = seri.slice(0, 1);
    if (persen) {
        const total = label.map((_, j) => seri.reduce((t, s) => t + Math.abs(s.nilai[j] ?? 0), 0));
        seri = seri.map((s) => ({
            ...s,
            asli: s.nilai,
            nilai: s.nilai.map((v, j) => (v === null || !total[j] ? null : (Math.abs(v) / total[j]) * 100)),
        }));
    }

    // Grafik kombinasi: seri pilihan digambar sebagai garis di atas batang
    const sebagaiGaris = (s) => (jenis === "bar" || jenis === "bar-stacked") && !!(p.seriGaris || [])[s.ke];
    const kombinasi = seri.some(sebagaiGaris);

    const datasets = seri.map((s, urutan) => {
        if (lingkaran) {
            return {
                label: s.nama,
                data: s.nilai,
                backgroundColor: label.map((_, j) => warna(j)),
                // Tepi sewarna potongannya menutup celah tipis antarpotongan; garis pemisah putih
                // digambar pluginPemisahLingkaran
                borderColor: label.map((_, j) => warna(j)),
                borderWidth: 1,
            };
        }
        const w = warna(s.ke);
        if (sebagaiGaris(s)) {
            return {
                type: "line",
                label: s.nama,
                data: s.nilai,
                borderColor: w,
                backgroundColor: w,
                borderWidth: 3,
                pointRadius: mini ? 0 : 3.5,
                pointHoverRadius: 5,
                spanGaps: true,
                // Tidak ikut ditumpuk dengan batang; digambar dan didaftar di legenda paling depan
                stack: `garis-${s.ke}`,
                order: 0,
            };
        }
        return {
            order: 1,
            label: s.nama,
            data: s.nilai,
            // Nilai asli batang 100%, untuk tooltip
            ...(persen ? { asli: s.asli } : {}),
            backgroundColor: garis ? transparan(w, jenis === "line" ? 1 : jenis === "area" ? 0.25 : 0.55) : w,
            borderColor: w,
            borderWidth: garis ? 2.5 : 0,
            // Sudut bulat di batang bertumpuk membuat celah antarsegmen
            borderRadius: garis || tumpuk ? 0 : 3,
            maxBarThickness: 64,
            // Area bertumpuk: tiap seri mengisi sampai seri di bawahnya
            fill: jenis === "area" ? "origin" : jenis === "area-stacked" ? (urutan === 0 ? "origin" : "-1") : false,
            tension: p.halus ? 0.35 : 0,
            // Titik lebih kecil kalau datanya rapat (mis. data bulanan bertahun-tahun)
            pointRadius: garis && p.titik && !mini ? (label.length > 80 ? 2.5 : 3) : 0,
            // Titik berongga: bagian dalam putih, tepi berwarna seri
            ...(garis && p.titikBerongga ? { pointBackgroundColor: "#ffffff", pointBorderWidth: 2 } : {}),
            spanGaps: true,
        };
    });

    const sumbuNilai = {
        display: !mini,
        stacked: tumpuk,
        // Batang selalu dari nol supaya panjangnya jujur
        // (area bertumpuk juga: tumpukannya dihitung dari nol)
        beginAtZero: garis && jenis !== "area-stacked" ? p.mulaiNol && !mini : true,
        grid: { display: p.garisBantu !== false, color: "#e5e7eb" },
        border: { display: false },
        ticks: { callback: (v) => (persen ? formatPersen(v) : formatRingkas(v)) },
        ...(persen ? { max: 100 } : {}),
        title: { display: !mini && !!p.judulSumbuNilai, text: p.judulSumbuNilai },
    };
    const sumbuLabel = {
        display: !mini,
        stacked: tumpuk,
        grid: { display: false },
        title: { display: !mini && !!p.judulSumbuLabel, text: p.judulSumbuLabel },
    };

    return {
        type: TIPE[jenis],
        data: { labels: label, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            indexAxis: horizontal ? "y" : "x",
            ...(jenis === "donut" ? { cutout: "55%" } : {}),
            // Garis, bertumpuk, dan kombinasi: tooltip menampilkan semua seri di label yang sama
            ...(garis || tumpuk || kombinasi ? { interaction: { mode: "index", intersect: false } } : {}),
            ...(mini ? { events: [] } : {}),
            // Ruang untuk label nilai di atas batang/titik dan di ujung grafik
            layout: {
                padding: labelNilai && !lingkaran
                    ? { top: 18, right: horizontal ? 56 : 24, left: garis ? 24 : 0 }
                    : 0,
            },
            plugins: {
                legend: {
                    // Lebih dari 30 potongan, legenda memenuhi grafik dan tidak terbaca lagi
                    display: !mini && p.legenda !== "sembunyi" && (lingkaran ? label.length <= 30 : seri.length > 1),
                    position: { atas: "top", bawah: "bottom", kanan: "right" }[p.legenda] || "top",
                    labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const nilai = lingkaran ? ctx.parsed : horizontal ? ctx.parsed.x : ctx.parsed.y;
                            const teks = persen
                                ? `${formatPersen(nilai)} (${format(ctx.dataset.asli?.[ctx.dataIndex] ?? 0)})`
                                : format(nilai);
                            return ` ${lingkaran ? ctx.label : ctx.dataset.label}: ${teks}`;
                        },
                    },
                },
                labelNilai: { tampil: labelNilai, format: persen ? formatPersen : formatRingkas, tumpuk: tumpuk && !garis },
                // Lebih dari 30 potongan, garis pemisah hanya membuat pola acak; warnanya sudah cukup membedakan
                pemisahLingkaran: { tampil: lingkaran && label.length <= 30, lebar: mini ? 1 : 2 },
            },
            scales: lingkaran ? {} : horizontal ? { x: sumbuNilai, y: sumbuLabel } : { x: sumbuLabel, y: sumbuNilai },
        },
    };
}

// Baca file CSV (pemisah koma, titik koma, atau tab ditebak dari baris pertama)
function bacaCsv(teks) {
    teks = teks.replace(/^﻿/, "");
    const barisPertama = teks.split(/\r?\n/)[0];
    const pemisah = [";", "\t", ","].sort((a, b) => barisPertama.split(b).length - barisPertama.split(a).length)[0];

    const hasil = [];
    let baris = [];
    let sel = "";
    let kutip = false;
    for (let i = 0; i < teks.length; i++) {
        const c = teks[i];
        if (kutip) {
            if (c === '"' && teks[i + 1] === '"') {
                sel += '"';
                i++;
            } else if (c === '"') {
                kutip = false;
            } else {
                sel += c;
            }
        } else if (c === '"') {
            kutip = true;
        } else if (c === pemisah) {
            baris.push(sel);
            sel = "";
        } else if (c === "\n" || c === "\r") {
            if (c === "\r" && teks[i + 1] === "\n") i++;
            baris.push(sel);
            hasil.push(baris);
            baris = [];
            sel = "";
        } else {
            sel += c;
        }
    }
    if (sel !== "" || baris.length) {
        baris.push(sel);
        hasil.push(baris);
    }
    return hasil.filter((b) => b.some((s) => s.trim() !== ""));
}

// Pecah teks menjadi beberapa baris yang muat di lebar tertentu (untuk unduhan PNG)
function bungkusTeks(ctx, teks, lebar) {
    const hasil = [];
    let baris = "";
    for (const kata of String(teks).split(/\s+/).filter(Boolean)) {
        const coba = baris ? baris + " " + kata : kata;
        if (ctx.measureText(coba).width > lebar && baris) {
            hasil.push(baris);
            baris = kata;
        } else {
            baris = coba;
        }
    }
    if (baris) hasil.push(baris);
    return hasil;
}

// Satu baris teks kanvas sesuai perataan judul (kiri, tengah, kanan, rata). Rata kiri-kanan (justify):
// jarak antarkata dilebarkan sampai baris menempel di kedua tepi, termasuk baris terakhir
function tulisBarisRata(ctx, teks, x, y, lebar, rata) {
    if (rata === "rata" && teks.includes(" ")) {
        const kata = teks.split(" ");
        const lebarKata = kata.map((k) => ctx.measureText(k).width);
        const sela = kata.length > 1 ? (lebar - lebarKata.reduce((a, b) => a + b, 0)) / (kata.length - 1) : 0;
        ctx.textAlign = "left";
        let posisi = x;
        kata.forEach((k, i) => {
            ctx.fillText(k, posisi, y);
            posisi += lebarKata[i] + sela;
        });
        return;
    }
    ctx.textAlign = { tengah: "center", kanan: "right" }[rata] || "left";
    ctx.fillText(teks, { tengah: x + lebar / 2, kanan: x + lebar }[rata] ?? x, y);
}

// Untuk halaman publik /grafik/{id} (tanpa Alpine)
window.GrafikQC = {
    pasang: (kanvas, spek) => new Chart(kanvas, konfigurasi(spek)),
    konfigurasi,
};

document.addEventListener("alpine:init", () => {
    // Objek Chart disimpan di closure, bukan di data Alpine: proxy reaktif Alpine merusak Chart.js

    // Grafik kecil di daftar grafik
    window.Alpine.data("grafikMini", (spek) => {
        let grafik = null;
        return {
            init() {
                grafik = new Chart(this.$refs.kanvas, konfigurasi(spek, true));
            },
            destroy() {
                grafik?.destroy();
            },
        };
    });

    // Editor grafik (halaman tambah/ubah grafik)
    window.Alpine.data("editorGrafik", (awal, opsi) => {
        let grafik = null;
        let jenisDigambar = null;
        let timerPesan = null;
        const dataContoh = awal.id ? null : JSON.stringify(awal.data);

        return {
            id: awal.id,
            judul: awal.judul,
            subjudul: awal.subjudul,
            jenis: awal.jenis,
            kolom: awal.data.kolom,
            baris: awal.data.baris,
            pengaturan: awal.pengaturan,
            terbit: awal.terbit,

            palet: PALET,
            tab: "pratinjau",
            perangkat: "desktop",
            menyimpan: false,
            kosong: false,
            disalin: false,
            // Isi tetap disimpan saat pemberitahuan memudar, supaya kotaknya tidak kosong sebelum hilang
            pesan: { teks: "", galat: false, tampil: false },
            terakhirDisimpan: null,
            terbuka: { template: true, kombinasi: true, warna: true, teks: true, legenda: false, angka: false, sumbu: false, garis: false, ukuran: false, bagikan: true },

            init() {
                // Grafik baru selalu dianggap belum disimpan
                this.terakhirDisimpan = this.id ? JSON.stringify(this.isian()) : "";
            },

            isian() {
                return {
                    judul: this.judul,
                    subjudul: this.subjudul,
                    jenis: this.jenis,
                    kolom: this.kolom,
                    baris: this.baris,
                    pengaturan: this.pengaturan,
                    terbit: this.terbit,
                };
            },

            get berubah() {
                return JSON.stringify(this.isian()) !== this.terakhirDisimpan;
            },
            // Grafik baru yang tabelnya belum diganti dari contoh data
            get masihContoh() {
                return dataContoh !== null && JSON.stringify({ kolom: this.kolom, baris: this.baris }) === dataContoh;
            },
            get kelasRataJudul() {
                return { tengah: "text-center", kanan: "text-right", rata: "text-justify [text-align-last:justify]" }[this.pengaturan.rataJudul] || "";
            },
            get lingkaran() {
                return this.jenis === "pie" || this.jenis === "donut";
            },
            get garis() {
                return this.jenis === "line" || this.jenis === "area" || this.jenis === "area-stacked";
            },
            get lebarPratinjau() {
                return { desktop: "100%", tablet: "768px", ponsel: "375px" }[this.perangkat];
            },
            // Yang warnanya bisa diatur satu per satu: seri (kolom B dst.), atau potongan lingkaran/donat
            get namaWarna() {
                if (this.lingkaran) return this.baris.map((b) => String(b[0] ?? "").trim()).filter(Boolean).slice(0, 30);
                return this.kolom.slice(1).map((k, i) => String(k ?? "").trim() || `Seri ${i + 1}`);
            },
            warnaSeri(i) {
                const palet = (PALET[this.pengaturan.palet] || PALET.quitcoal).warna;
                return this.pengaturan.warnaKhusus?.[i] || palet[i % palet.length];
            },
            aturWarna(i, warna) {
                const daftar = [...(this.pengaturan.warnaKhusus || [])];
                while (daftar.length <= i) daftar.push("");
                daftar[i] = warna;
                this.pengaturan.warnaKhusus = daftar;
            },
            // Pemisah desimal hasil tebakan dari isi tabel (untuk pilihan "Otomatis" di tab Data)
            get pemisahTebakan() {
                return tebakPemisah(this.baris);
            },
            get adaWarnaKhusus() {
                return (this.pengaturan.warnaKhusus || []).some(Boolean);
            },
            // Palet hanya ditandai terpilih kalau tidak ada warna yang diatur satu per satu
            paletAktif(kunci) {
                return this.pengaturan.palet === kunci && !this.adaWarnaKhusus;
            },
            // Memilih palet mengganti semua warna, termasuk warna yang diatur satu per satu
            pilihPalet(kunci) {
                this.pengaturan.palet = kunci;
                this.pengaturan.warnaKhusus = [];
            },
            get urlTampil() {
                return opsi.urlTampil.replace("__ID__", this.id);
            },
            get kodeEmbed() {
                const judul = String(this.judul).replace(/"/g, "&quot;");
                return `<iframe src="${this.urlTampil}" title="${judul}" width="100%" height="${this.pengaturan.tinggi + 170}" style="border:0" loading="lazy"></iframe>`;
            },

            // Pratinjau, dipanggil x-effect: digambar ulang setiap isi atau pengaturan grafik berubah
            gambar() {
                const spek = JSON.parse(JSON.stringify({
                    jenis: this.jenis,
                    data: { kolom: this.kolom, baris: this.baris },
                    pengaturan: this.pengaturan,
                }));
                const cfg = konfigurasi(spek);
                this.kosong = cfg.data.labels.length === 0 || cfg.data.datasets.length === 0;

                if (grafik && jenisDigambar === spek.jenis) {
                    grafik.data = cfg.data;
                    grafik.options = cfg.options;
                    grafik.update();
                } else {
                    grafik?.destroy();
                    grafik = new Chart(this.$refs.kanvas, cfg);
                    jenisDigambar = spek.jenis;
                }
            },

            beriPesan(teks, galat = false) {
                this.pesan = { teks, galat, tampil: true };
                clearTimeout(timerPesan);
                timerPesan = setTimeout(() => (this.pesan.tampil = false), galat ? 6000 : 2500);
            },

            async simpan() {
                if (this.menyimpan) return;
                this.menyimpan = true;
                const isian = JSON.parse(JSON.stringify(this.isian()));
                try {
                    const hasil = await this.$wire.simpan(isian);
                    if (!hasil?.ok) {
                        this.beriPesan(hasil?.pesan || "Gagal menyimpan grafik.", true);
                        return false;
                    }
                    this.terakhirDisimpan = JSON.stringify(isian);
                    if (!this.id) {
                        // Grafik baru: lanjut di halaman ubah supaya simpan berikutnya memperbarui grafik ini
                        window.location.href = hasil.url;
                        return true;
                    }
                    this.beriPesan("Grafik tersimpan");
                    return true;
                } catch (err) {
                    this.beriPesan("Gagal menyimpan grafik. Periksa koneksi lalu coba lagi.", true);
                    return false;
                } finally {
                    this.menyimpan = false;
                }
            },

            async aturTerbit(nilai) {
                const sebelumnya = this.terbit;
                this.terbit = nilai;
                if (!(await this.simpan())) this.terbit = sebelumnya;
            },

            async salinEmbed() {
                await navigator.clipboard.writeText(this.kodeEmbed);
                this.disalin = true;
                setTimeout(() => (this.disalin = false), 2000);
            },

            // ---- Tabel data ----

            hurufKolom(i) {
                return String.fromCharCode(65 + i);
            },

            tambahBaris() {
                this.baris.push(this.kolom.map(() => ""));
            },

            tambahKolom() {
                this.kolom.push("");
                this.baris.forEach((b) => b.push(""));
            },

            hapusBaris(i) {
                this.baris.splice(i, 1);
                if (!this.baris.length) this.tambahBaris();
            },

            hapusKolom(c) {
                this.kolom.splice(c, 1);
                this.baris.forEach((b) => b.splice(c, 1));
            },

            // Enter di sel: turun ke baris berikutnya (ditambah kalau sudah paling bawah)
            turun(i, c) {
                if (i + 1 >= this.baris.length) this.tambahBaris();
                this.$nextTick(() => this.$root.querySelector(`[data-sel="${i + 1}-${c}"]`)?.focus());
            },

            // Tempel beberapa sel sekaligus dari Excel/Google Sheets (dipisah tab dan baris baru),
            // mulai dari sel yang dipilih. i = -1: mulai dari baris nama kolom.
            tempel(e, i, c) {
                const teks = (e.clipboardData?.getData("text/plain") ?? "").replace(/\r/g, "").replace(/\n$/, "");
                if (!/[\t\n]/.test(teks)) return;
                e.preventDefault();

                const tabel = teks.split("\n").map((b) => b.split("\t"));
                const lebar = c + Math.max(...tabel.map((b) => b.length));
                while (this.kolom.length < Math.min(lebar, opsi.maksKolom)) this.tambahKolom();
                tabel.forEach((isi, k) => {
                    const r = i + k;
                    if (r >= 0) while (this.baris.length <= r) this.tambahBaris();
                    isi.forEach((nilai, j) => {
                        if (c + j >= this.kolom.length) return;
                        if (r < 0) this.kolom[c + j] = nilai.trim();
                        else this.baris[r][c + j] = nilai.trim();
                    });
                });
                this.beriPesan(`${tabel.length} baris ditempel`);
            },

            async unggahCsv(e) {
                const file = e.target.files[0];
                e.target.value = "";
                if (!file) return;

                const tabel = bacaCsv(await file.text());
                const lebar = Math.min(Math.max(2, ...tabel.map((b) => b.length)), opsi.maksKolom);
                if (tabel.length < 2) {
                    this.beriPesan("File tidak berisi data. Baris pertama = nama kolom, baris berikutnya = data.", true);
                    return;
                }
                const rapikan = (b) => Array.from({ length: lebar }, (_, k) => String(b[k] ?? "").trim());
                this.kolom = rapikan(tabel[0]);
                this.baris = tabel.slice(1).map(rapikan);
                this.tab = "data";
                this.beriPesan(`${this.baris.length} baris dimuat dari ${file.name}`);
            },

            // ---- Unduh PNG: judul, subjudul, grafik, dan sumber digabung dalam satu gambar ----
            async unduhPng() {
                if (!grafik || this.kosong) {
                    this.beriPesan("Belum ada grafik untuk diunduh.", true);
                    return;
                }
                await document.fonts?.ready;

                const kanvasGrafik = this.$refs.kanvas;
                const lebar = kanvasGrafik.clientWidth;
                const tinggiGrafik = kanvasGrafik.clientHeight;
                const tepi = 24;
                const skala = 2;
                const keluaran = document.createElement("canvas");
                const ctx = keluaran.getContext("2d");
                const font = Chart.defaults.font.family;

                const p = this.pengaturan;
                const kaki = [p.sumber ? (p.bahasa === "en" ? "Source: " : "Sumber: ") + p.sumber : "", p.catatan].filter(Boolean).join(" · ");
                const bagian = [
                    { teks: this.judul, font: `600 20px ${font}`, warna: "#111827", tinggi: 28 },
                    { teks: this.subjudul, font: `400 14px ${font}`, warna: "#6b7280", tinggi: 20 },
                ].map((b) => {
                    ctx.font = b.font;
                    return { ...b, baris: b.teks ? bungkusTeks(ctx, b.teks, lebar) : [] };
                });
                ctx.font = `400 12px ${font}`;
                const barisKaki = kaki ? bungkusTeks(ctx, kaki, lebar) : [];

                const tinggiAtas = bagian.reduce((t, b) => t + b.baris.length * b.tinggi, 0);
                const tinggi = tepi + tinggiAtas + (tinggiAtas ? 16 : 0) + tinggiGrafik + (barisKaki.length ? 16 + barisKaki.length * 16 : 0) + tepi;
                keluaran.width = (lebar + tepi * 2) * skala;
                keluaran.height = tinggi * skala;
                ctx.scale(skala, skala);
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, lebar + tepi * 2, tinggi);

                let y = tepi;
                ctx.textBaseline = "top";
                for (const b of bagian) {
                    ctx.font = b.font;
                    ctx.fillStyle = b.warna;
                    for (const t of b.baris) {
                        tulisBarisRata(ctx, t, tepi, y, lebar, p.rataJudul);
                        y += b.tinggi;
                    }
                }
                ctx.textAlign = "left";
                if (tinggiAtas) y += 16;
                ctx.drawImage(kanvasGrafik, tepi, y, lebar, tinggiGrafik);
                y += tinggiGrafik + 16;
                ctx.font = `400 12px ${font}`;
                ctx.fillStyle = "#6b7280";
                for (const t of barisKaki) {
                    ctx.fillText(t, tepi, y);
                    y += 16;
                }

                const tautan = document.createElement("a");
                tautan.href = keluaran.toDataURL("image/png");
                tautan.download = (this.judul || "grafik").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") + ".png";
                tautan.click();
            },
        };
    });
});
