import { animate, inView, stagger } from 'framer-motion/dom'

// ─── NAVBAR ──────────────────────────────────────────────────────────────────
function initNavbar() {
  const nav = document.querySelector('.st-nav')
  if (!nav) return
  let prev = false
  const update = () => {
    const on = window.scrollY > 60
    if (on === prev) return
    prev = on
    nav.classList.toggle('nav-scrolled', on)
  }
  window.addEventListener('scroll', update, { passive: true })
  update()
}

// ─── HERO ENTRANCE ───────────────────────────────────────────────────────────
function initHero() {
  const words = document.querySelectorAll('.hero-word')
  if (words.length) {
    animate(
      words,
      { opacity: [0, 1], y: [70, 0], filter: ['blur(8px)', 'blur(0px)'] },
      { delay: stagger(0.07), duration: 0.75, ease: [0.22, 1, 0.36, 1] }
    )
  }
  const sub = document.querySelector('.hero-sub')
  if (sub) {
    animate(sub, { opacity: [0, 1], y: [30, 0] }, {
      delay: 0.55, duration: 0.7, ease: [0.22, 1, 0.36, 1]
    })
  }
  const bar = document.querySelector('.hero-search')
  if (bar) {
    animate(bar, { opacity: [0, 1], y: [45, 0], scale: [0.96, 1] }, {
      delay: 0.75, duration: 0.8, ease: [0.22, 1, 0.36, 1]
    })
  }
  const badge = document.querySelector('.hero-badge')
  if (badge) {
    animate(badge, { opacity: [0, 1], x: [-20, 0] }, {
      delay: 0.3, duration: 0.6, ease: [0.22, 1, 0.36, 1]
    })
  }
}

// ─── SCROLL REVEAL ───────────────────────────────────────────────────────────
function initScrollReveal() {
  // Stagger groups
  document.querySelectorAll('[data-stagger]').forEach(group => {
    const items = group.querySelectorAll('[data-reveal]')
    if (!items.length) return
    inView(group, () => {
      animate(
        items,
        { opacity: [0, 1], y: [55, 0] },
        { delay: stagger(0.13), duration: 0.7, ease: [0.22, 1, 0.36, 1] }
      )
    }, { margin: '-8% 0px' })
  })

  // Solo reveals (not inside a stagger group)
  document.querySelectorAll('[data-reveal]:not([data-stagger] [data-reveal])').forEach(el => {
    inView(el, () => {
      animate(el, { opacity: [0, 1], y: [40, 0] }, {
        duration: 0.75, ease: [0.22, 1, 0.36, 1]
      })
    }, { margin: '-5% 0px' })
  })

  // Scale-in reveals
  document.querySelectorAll('[data-reveal-scale]').forEach(el => {
    inView(el, () => {
      animate(el, { opacity: [0, 1], scale: [0.88, 1] }, {
        duration: 0.7, ease: [0.34, 1.56, 0.64, 1]
      })
    }, { margin: '-5% 0px' })
  })
}

// ─── 3-D CARD TILT ───────────────────────────────────────────────────────────
function init3DCards() {
  document.querySelectorAll('[data-tilt]').forEach(card => {
    card.style.willChange = 'transform'

    card.addEventListener('mousemove', e => {
      const r = card.getBoundingClientRect()
      const x = (e.clientX - r.left) / r.width  - 0.5
      const y = (e.clientY - r.top)  / r.height - 0.5
      animate(card, {
        rotateX: -y * 13,
        rotateY:  x * 13,
        scale: 1.035,
        boxShadow: `${-x * 20}px ${-y * 20}px 60px rgba(0,0,0,0.22), 0 0 0 1px rgba(245,158,11,0.15)`
      }, { duration: 0.12, ease: 'linear' })
    })

    card.addEventListener('mouseleave', () => {
      animate(card, {
        rotateX: 0, rotateY: 0, scale: 1,
        boxShadow: '0 4px 24px rgba(0,0,0,0.1)'
      }, { duration: 0.65, ease: [0.34, 1.56, 0.64, 1] })
    })
  })
}

// ─── ANIMATED COUNTERS ───────────────────────────────────────────────────────
function initCounters() {
  document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseFloat(el.dataset.counter)
    const isFloat = el.dataset.counter.includes('.')
    let done = false
    inView(el, () => {
      if (done) return; done = true
      const duration = 2200
      const t0 = performance.now()
      const tick = now => {
        const p = Math.min((now - t0) / duration, 1)
        const eased = 1 - Math.pow(1 - p, 4)
        const val = eased * target
        el.textContent = isFloat ? val.toFixed(1) : Math.round(val).toLocaleString()
        if (p < 1) requestAnimationFrame(tick)
      }
      requestAnimationFrame(tick)
    })
  })
}

// ─── PARALLAX HERO ───────────────────────────────────────────────────────────
function initParallax() {
  const bg = document.querySelector('.hero-bg-img')
  if (!bg) return
  window.addEventListener('scroll', () => {
    bg.style.transform = `translateY(${window.scrollY * 0.38}px)`
  }, { passive: true })
}

// ─── HOVER LIFT ──────────────────────────────────────────────────────────────
function initHoverLift() {
  document.querySelectorAll('[data-hover-lift]').forEach(el => {
    el.addEventListener('mouseenter', () =>
      animate(el, { y: -5 }, { duration: 0.3, ease: [0.22, 1, 0.36, 1] }))
    el.addEventListener('mouseleave', () =>
      animate(el, { y:  0 }, { duration: 0.4, ease: [0.22, 1, 0.36, 1] }))
  })
}

// ─── SMOOTH IMAGE REVEAL ─────────────────────────────────────────────────────
function initImageReveal() {
  document.querySelectorAll('[data-img-reveal]').forEach(wrap => {
    inView(wrap, () => {
      animate(wrap, { clipPath: ['inset(0 100% 0 0)', 'inset(0 0% 0 0)'] }, {
        duration: 0.9, ease: [0.22, 1, 0.36, 1]
      })
    }, { margin: '-5% 0px' })
  })
}

// ─── FLOATING ELEMENTS ───────────────────────────────────────────────────────
function initFloating() {
  document.querySelectorAll('[data-float]').forEach((el, i) => {
    const dur = 3 + i * 0.7
    const loop = () => {
      animate(el, { y: [-6, 6] }, {
        duration: dur, repeat: Infinity, repeatType: 'mirror', ease: 'easeInOut'
      })
    }
    loop()
  })
}

// ─── BOOT ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initNavbar()
  initHero()
  initScrollReveal()
  init3DCards()
  initCounters()
  initParallax()
  initHoverLift()
  initImageReveal()
  initFloating()
})
