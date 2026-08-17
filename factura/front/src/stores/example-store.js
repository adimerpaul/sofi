import { defineStore, acceptHMRUpdate } from 'pinia'

export const useCounterStore = defineStore('counter', {
  state: () => ({
    counter: 0,
    isLogged: !!localStorage.getItem('tokenProvidencia'),
    user: {},
    permissions: [],
    reservas: [],
    socketReservas: null,
    env: {
      razon: import.meta.env.VITE_RAZON,
      direccion: import.meta.env.VITE_DIR,
      telefono: import.meta.env.VITE_TEL,
    },
  }),

  getters: {
    doubleCount: (state) => state.counter * 2
  },

  actions: {
    increment() {
      this.counter++
    }
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCounterStore, import.meta.hot))
}
