/**
 * Facade de servicios del admin (staff).
 *
 * Antes vivía en el barrel compartido como `v2.staff.*`. Se movió aquí para que
 * el layer compartido (`services/v2`) NO dependa del módulo admin: el admin es
 * dueño de sus propios servicios y los expone por este facade.
 *
 * Uso:
 *   import { staff } from '@/modules/admin/services'
 *   await staff.application.list()
 */
import auth from './auth.staff.service'
import application from './application.staff.service'
import document from './document.staff.service'
import user from './user.staff.service'
import product from './product.staff.service'
import config from './config.staff.service'
import apiLog from './apilog.staff.service'
import tenant from './tenant.staff.service'
import integration from './integration.staff.service'
import activity from './activity.staff.service'
import loan from './loan.staff.service'

export const staff = {
  auth,
  application,
  document,
  user,
  product,
  config,
  apiLog,
  tenant,
  integration,
  activity,
  loan,
}

export default staff
