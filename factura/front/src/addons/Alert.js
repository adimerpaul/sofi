import {Dialog, Notify} from 'quasar'
import Icon from "components/Icon.vue";

export class Alert{
  static success(message,subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 1500,
      icon: 'check_circle',
      iconColor: 'positive',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      progressClass: 'bg-positive',
      classes: 'bg-white text-black text-bold left-green-border'
    });
  }
  static error(message,subTitle = '') {
    Notify.create({
      progress: true,
      color: 'white',
      textColor: 'black',
      position: 'top',
      message,
      caption: subTitle,
      timeout: 1500,
      icon: 'error',
      iconColor: 'negative',
      actions: [
        { icon: 'close', color: 'black', round: true, size: 'xs' }
      ],
      progressClass: 'bg-negative',
      classes: 'bg-white text-black text-bold left-red-border'
    });
  }
  static dialog (title, message) {
    // return Dialog.create({
    //   title: 'Confirmación',
    //   component: undefined,
    //   message,
    //   // position: 'top',
    //   color: 'positive',
    //   ok: {
    //     label: 'Aceptar',
    //     color: 'positive'
    //   },
    //   cancel: {
    //     label: 'Cancelar',
    //     color: 'negative'
    //   },
    // })
    // this.$q.dialog({
    //   title: 'Anular Pago',
    //   message: '¿Está seguro de anular el pago?',
    //   cancel: true,
    //   component: Icon,
    // })

    return Dialog.create({
      component: Icon,
      componentProps: {
        title: title,
        message: message,
        icon: 'warning',
        color: 'negative',
      }
    })
  }
  static dialogPrompt (message) {
    return Dialog.create({
      title: 'Confirmación',
      message,
      // position: 'top',
      color: 'positive',
      ok: {
        label: 'Aceptar',
        color: 'positive'
      },
      cancel: {
        label: 'Cancelar',
        color: 'negative'
      },
      prompt: {
        model: '',
        type: 'text',
        label: 'Ingrese el texto',
        // required: true,
        // rules: [(val) => val !== '']
      }
    })
  }
  static dialogPromptPassword (message) {
    return Dialog.create({
      title: 'Confirmación',
      message,
      // position: 'top',
      color: 'positive',
      ok: {
        label: 'Aceptar',
        color: 'positive'
      },
      cancel: {
        label: 'Cancelar',
        color: 'negative'
      },
      prompt: {
        model: '',
        type: 'password',
        label: 'Ingrese el texto',
        // required: true,
        // rules: [(val) => val !== '']
      }
    })
  }
  static dialogAnular(message) {
    return Dialog.create({
      title: 'Anular Pago',
      message,
      html: true,
      // position: 'top',
      color: 'negative',
      ok: {
        label: 'Aceptar',
        color: 'negative'
      },
      cancel: {
        label: 'Cancelar',
        color: 'positive'
      },
    })
  }
  static dialogConfirm(message) {
    return Dialog.create({
      title: 'Confirmación',
      message,
      html: true,
      // position: 'top',
      color: 'positive',
      ok: {
        label: 'Aceptar',
        color: 'positive'
      },
      cancel: {
        label: 'Cancelar',
        color: 'negative'
      },
    })
  }

}
